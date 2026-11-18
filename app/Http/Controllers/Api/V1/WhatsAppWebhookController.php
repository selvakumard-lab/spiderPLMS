<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\EvaluateClientChat;
use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\Project;
use App\Models\Tenant;
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

        $payload = (array) $request->json()->all();
        $tenantId = $whatsapp->tenantIdFromInstance((string) ($payload['instance'] ?? ''));
        $tenant = $tenantId === null ? null : Tenant::with('plan')->find($tenantId);

        // Each workspace has its own linked phone; deliveries for unknown instances or workspaces off the plan are ignored.
        if ($tenant === null || ! $tenant->planAllows('whatsapp_chat')) {
            return response()->json(['received' => false]);
        }

        $touched = [];

        foreach ($whatsapp->incomingMessages($payload) as $incoming) {
            $client = Client::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('whatsapp_number', $incoming['from'])->first();
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
}
