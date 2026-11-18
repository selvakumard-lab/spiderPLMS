<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\EvaluateClientChat;
use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\Project;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Evolution API webhook: incoming client messages. Evolution calls it with the secret token in the address.
 */
class WhatsAppWebhookController extends Controller
{
    public function receive(Request $request, WhatsAppService $whatsapp): JsonResponse
    {
        abort_unless($whatsapp->isValidToken($request->query('token')), 403);

        $touched = [];

        foreach ($whatsapp->incomingMessages((array) $request->json()->all()) as $incoming) {
            $client = $this->clientFor($incoming['from']);
            if ($client === null || ClientMessage::withoutGlobalScopes()->where('channel', 'whatsapp')->where('provider_message_id', $incoming['id'])->exists()) {
                continue;
            }

            $project = Project::withoutGlobalScopes()->where('tenant_id', $client->tenant_id)->where('client_id', $client->id)
                ->orderByRaw("case when status = 'active' then 0 else 1 end")->latest('updated_at')->first();

            ClientMessage::withoutGlobalScopes()->create([
                'tenant_id' => $client->tenant_id,
                'project_id' => $project?->id,
                'client_id' => $client->id,
                'channel' => 'whatsapp',
                'direction' => 'in',
                'body' => $incoming['text'],
                'to_address' => $incoming['from'],
                'status' => 'received',
                'provider_message_id' => $incoming['id'],
            ]);

            if ($project !== null) {
                $touched[$project->id] = true;
            }
        }

        // Evaluate after the response so Evolution gets its 200 straight away.
        foreach (array_keys($touched) as $projectId) {
            EvaluateClientChat::dispatchAfterResponse($projectId);
        }

        return response()->json(['received' => true]);
    }

    /** The client with this WhatsApp number. If several workspaces share it, the most recently messaged one wins. */
    private function clientFor(string $number): ?Client
    {
        $digits = preg_replace('/\D/', '', $number);
        $clients = Client::withoutGlobalScopes()->where('whatsapp_number', $digits)->get();

        if ($clients->count() <= 1) {
            return $clients->first();
        }

        $recent = ClientMessage::withoutGlobalScopes()->whereIn('client_id', $clients->pluck('id'))->latest('id')->value('client_id');

        return $clients->firstWhere('id', $recent) ?? $clients->first();
    }
}
