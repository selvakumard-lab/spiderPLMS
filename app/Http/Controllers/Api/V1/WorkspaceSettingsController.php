<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\DeepSeekService;
use App\Services\WhatsAppService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Workspace preferences: the knowledge base DeepSeek reads, email tone and signature, automation.
 */
class WorkspaceSettingsController extends Controller
{
    public function show(Request $request, DeepSeekService $ai, WhatsAppService $whatsapp): JsonResponse
    {
        return response()->json($this->payload($request, $ai, $whatsapp));
    }

    public function update(Request $request, DeepSeekService $ai, WhatsAppService $whatsapp): JsonResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403, 'Only an organization admin can change workspace settings.');

        $data = $request->validate([
            'knowledge_base' => ['sometimes', 'nullable', 'string', 'max:8000'],
            'tone' => ['sometimes', Rule::in(['professional', 'friendly', 'formal'])],
            'sender_name' => ['sometimes', 'string', 'max:120'],
            'email_signature' => ['sometimes', 'nullable', 'string', 'max:500'],
            'auto_draft' => ['sometimes', 'boolean'],
            'email_updates' => ['sometimes', 'boolean'],
        ]);

        $tenant = app(TenantContext::class)->get();
        $tenant->saveWorkspaceSettings(array_map(fn ($v) => $v ?? '', $data));
        ActivityLog::record('workspace.settings.updated', $tenant, ['fields' => array_keys($data)], $request->user()->id);

        return response()->json($this->payload($request, $ai, $whatsapp));
    }

    /** @return array<string, mixed> */
    private function payload(Request $request, DeepSeekService $ai, WhatsAppService $whatsapp): array
    {
        $tenant = app(TenantContext::class)->get()->loadMissing('plan');

        return [
            'data' => $tenant->workspaceSettings(),
            'can_edit' => $request->user()->can('settings.manage'),
            'ai_configured' => $ai->isConfigured(),
            'whatsapp' => [
                'allowed' => $tenant->planAllows('whatsapp_chat'),
                'configured' => $whatsapp->isConfigured(),
                'state' => $tenant->planAllows('whatsapp_chat') ? $whatsapp->forTenant($tenant)->connectionState() : 'unavailable',
                'instance' => $tenant->whatsappInstance(),
            ],
        ];
    }
}
