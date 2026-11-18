<?php

namespace App\Services;

use App\Models\ChatEvaluation;
use App\Models\ClientMessage;
use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Reads a project's WhatsApp conversation with the client and records a short evaluation:
 * what was said, how the client feels, what is still open, and a suggested reply.
 */
class ChatEvaluationService
{
    private const SENTIMENTS = ['positive', 'neutral', 'negative', 'urgent'];

    public function __construct(private DeepSeekService $ai) {}

    public function evaluate(Project $project): ?ChatEvaluation
    {
        $messages = ClientMessage::where('project_id', $project->id)->where('channel', 'whatsapp')
            ->whereIn('status', ['sent', 'received'])->latest('id')->limit(30)->get()->reverse()->values();

        if ($messages->isEmpty()) {
            return null;
        }

        $tenant = Tenant::findOrFail($project->tenant_id);
        $settings = $tenant->workspaceSettings();

        $transcript = $messages->map(fn (ClientMessage $m) => sprintf('[%s] %s: %s', $m->created_at?->format('Y-m-d H:i'), $m->direction === 'in' ? 'CLIENT' : 'TEAM', $m->body))->implode("\n");

        $system = 'You evaluate a WhatsApp conversation between a delivery team and their client for a project. '
            .'Reply with JSON only: {"summary": "2-3 sentences", "sentiment": "positive|neutral|negative|urgent", '
            .'"pending_items": ["things the client asked for or the team still owes, max 6"], "suggested_reply": "a short helpful reply from the team, or empty"}. '
            ."Tone for the reply: {$settings['tone']}. Use only what the conversation says."
            .($settings['knowledge_base'] !== '' ? "\n\nCompany knowledge base:\n".Str::limit($settings['knowledge_base'], 4000, '') : '');

        $data = $this->ai->completeJson($system, "Project: {$project->name}\n\nConversation:\n{$transcript}");

        if ($data !== null && filled($data['summary'] ?? null)) {
            $sentiment = in_array($data['sentiment'] ?? null, self::SENTIMENTS, true) ? $data['sentiment'] : 'neutral';

            return $this->store($project, $messages, [
                'summary' => trim((string) $data['summary']),
                'sentiment' => $sentiment,
                'pending_items' => collect((array) ($data['pending_items'] ?? []))->filter(fn ($i) => is_string($i) && $i !== '')->take(6)->values()->all(),
                'suggested_reply' => trim((string) ($data['suggested_reply'] ?? '')) ?: null,
                'source' => 'deepseek',
            ]);
        }

        $lastFromClient = $messages->where('direction', 'in')->last();

        return $this->store($project, $messages, [
            'summary' => sprintf('%d messages in this chat. %s (Rule-based summary, add a DeepSeek key for an AI evaluation.)', $messages->count(), $lastFromClient ? 'Latest from the client: "'.Str::limit($lastFromClient->body, 140).'"' : 'The client has not replied yet.'),
            'sentiment' => 'neutral',
            'pending_items' => [],
            'suggested_reply' => null,
            'source' => 'fallback',
        ]);
    }

    /**
     * @param  Collection<int, ClientMessage>  $messages
     * @param  array<string, mixed>  $result
     */
    private function store(Project $project, $messages, array $result): ChatEvaluation
    {
        return ChatEvaluation::create($result + [
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'client_id' => $project->client_id,
            'messages_considered' => $messages->count(),
        ]);
    }
}
