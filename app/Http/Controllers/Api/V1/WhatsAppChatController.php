<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientMessage;
use App\Services\ClientCommunicationService;
use App\Services\WhatsAppService;
use App\Support\Tenancy\TenantContext;
use App\Support\TrackerPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * The workspace's WhatsApp inbox: link a phone by scanning a QR code, then chat with clients.
 * Every workspace gets its own Evolution instance, and the whole feature needs a plan that includes WhatsApp chat.
 */
class WhatsAppChatController extends Controller
{
    private const THREAD_STATUSES = ['sent', 'received', 'failed'];

    public function __construct(private WhatsAppService $whatsapp, private ClientCommunicationService $communication) {}

    /** Plan access and connection state. Reachable on every plan so the screen can explain an upgrade. */
    public function status(): JsonResponse
    {
        $tenant = app(TenantContext::class)->get()->loadMissing('plan');
        $allowed = $tenant->planAllows('whatsapp_chat');
        $service = $this->whatsapp->forTenant($tenant);
        $state = $allowed ? $service->connectionState() : 'unavailable';

        return response()->json([
            'allowed' => $allowed,
            'plan' => $tenant->plan?->name,
            'configured' => $service->isConfigured(),
            'state' => $state,
            'number' => $allowed && $state === 'open' ? $service->linkedNumber() : null,
        ]);
    }

    /** Creates the workspace's instance when needed and returns a QR code to scan (null once a phone is linked). */
    public function connect(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403, 'Only an organization admin can link the WhatsApp number.');

        $service = $this->whatsapp->forTenant(app(TenantContext::class)->get());
        abort_unless($service->isConfigured(), 422, 'WhatsApp is not set up on this server yet. Ask the platform administrator.');

        try {
            if (! $service->instanceExists()) {
                $service->createInstance();
            }
            $service->registerWebhook($service->webhookUrl());

            $state = $service->connectionState();
            $qr = $state === 'open' ? null : $service->qrCode();
        } catch (Throwable $e) {
            report($e);
            abort(422, 'Could not reach the WhatsApp server. Try again shortly.');
        }

        return response()->json(['state' => $state, 'qr' => $qr]);
    }

    public function disconnect(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403, 'Only an organization admin can unlink the WhatsApp number.');

        try {
            $this->whatsapp->forTenant(app(TenantContext::class)->get())->logout();
        } catch (Throwable $e) {
            report($e);
            abort(422, 'Could not unlink the phone. Try again shortly.');
        }

        return response()->json(['message' => 'WhatsApp number unlinked.']);
    }

    /** Clients with a WhatsApp number or a conversation, newest conversation first. */
    public function conversations(Request $request): JsonResponse
    {
        $stats = ClientMessage::query()->where('channel', 'whatsapp')->whereIn('status', self::THREAD_STATUSES)->whereNotNull('client_id')
            ->selectRaw("client_id, max(id) as last_id, sum(case when direction = 'in' and read_at is null then 1 else 0 end) as unread")
            ->groupBy('client_id')->get()->keyBy('client_id');
        $last = ClientMessage::query()->whereIn('id', $stats->pluck('last_id'))->get()->keyBy('client_id');

        $search = trim((string) $request->query('search'));
        $digits = preg_replace('/\D/', '', $search);

        $rows = Client::query()
            ->where(fn ($q) => $q->whereNotNull('whatsapp_number')->orWhereIn('id', $stats->keys()))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereRaw('lower(name) like ?', ['%'.mb_strtolower($search).'%'])
                ->orWhereRaw('lower(contact_person) like ?', ['%'.mb_strtolower($search).'%'])
                ->when($digits !== '', fn ($n) => $n->orWhere('whatsapp_number', 'like', "%{$digits}%"))))
            ->get()
            ->map(function (Client $c) use ($stats, $last): array {
                $message = $last->get($c->id);

                return [
                    'client_id' => $c->id,
                    'name' => $c->name,
                    'contact_person' => $c->contact_person,
                    'number' => $c->whatsapp_number,
                    'unread' => (int) ($stats->get($c->id)->unread ?? 0),
                    'last_message' => $message ? [
                        'body' => $message->body,
                        'direction' => $message->direction,
                        'status' => $message->status,
                        'at' => ($message->sent_at ?? $message->created_at)?->toIso8601String(),
                    ] : null,
                ];
            })
            ->sortByDesc(fn (array $r) => $r['last_message']['at'] ?? '')->values();

        return response()->json(['data' => $rows, 'unread' => $rows->sum('unread')]);
    }

    /** One client's WhatsApp thread, oldest first. Opening it marks their messages as read. */
    public function thread(Client $client): JsonResponse
    {
        $query = ClientMessage::where('client_id', $client->id)->where('channel', 'whatsapp')->whereIn('status', self::THREAD_STATUSES);

        (clone $query)->where('direction', 'in')->whereNull('read_at')->update(['read_at' => now()]);

        $messages = $query->with('project:id,name,code')->latest('id')->limit(200)->get()->reverse()->values();

        return response()->json([
            'client' => ['id' => $client->id, 'name' => $client->name, 'contact_person' => $client->contact_person, 'number' => $client->whatsapp_number],
            'data' => $messages->map(fn (ClientMessage $m) => TrackerPresenter::message($m)),
        ]);
    }

    public function send(Request $request, Client $client): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        abort_if(blank($client->whatsapp_number), 422, 'This client has no WhatsApp number. Add one on the Clients page.');

        $message = $this->communication->sendChat($client, $data['body'], $request->user());

        return response()->json(['data' => TrackerPresenter::message($message)], $message->status === 'sent' ? 201 : 422);
    }
}
