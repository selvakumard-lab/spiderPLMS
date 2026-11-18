<?php

namespace App\Services;

use App\Mail\ClientUpdateMail;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Drafts client emails and WhatsApp messages with DeepSeek (rule-based text when the AI is unavailable)
 * and sends a draft once a person has reviewed it.
 */
class ClientCommunicationService
{
    public const REASONS = ['dependency_overdue', 'status_alert', 'progress_update', 'project_update', 'custom'];

    public function __construct(
        private DeepSeekService $ai,
        private ProjectTrackerService $tracker,
        private WhatsAppService $whatsapp,
    ) {}

    /** A ready-made `$body` (for example the AI's suggested chat reply) skips the writing step. */
    public function draft(Project $project, string $channel, string $reason, ?string $instruction = null, ?User $by = null, ?string $body = null): ClientMessage
    {
        $project->loadMissing('client');
        $tenant = Tenant::findOrFail($project->tenant_id);
        $facts = $this->facts($project);

        $generated = filled($body)
            ? ['subject' => $this->fallbackSubject($project, $reason), 'body' => trim($body), 'ai' => false]
            : $this->generate($tenant, $project, $channel, $reason, $instruction, $facts);

        return ClientMessage::create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'client_id' => $project->client_id,
            'created_by' => $by?->id,
            'channel' => $channel,
            'direction' => 'out',
            'reason' => $reason,
            'subject' => $channel === 'email' ? $generated['subject'] : null,
            'body' => $generated['body'],
            'to_address' => $channel === 'email' ? $project->client?->email : $project->client?->whatsapp_number,
            'status' => 'draft',
            'ai_generated' => $generated['ai'],
        ]);
    }

    /** Sends a chat message straight to a client's WhatsApp, with no draft step, and records it on their latest project. */
    public function sendChat(Client $client, string $body, ?User $by = null): ClientMessage
    {
        $project = Project::where('client_id', $client->id)->orderByRaw("case when status = 'active' then 0 else 1 end")->latest('updated_at')->first();

        $message = ClientMessage::create([
            'tenant_id' => $client->tenant_id,
            'project_id' => $project?->id,
            'client_id' => $client->id,
            'created_by' => $by?->id,
            'channel' => 'whatsapp',
            'direction' => 'out',
            'reason' => 'custom',
            'body' => trim($body),
            'to_address' => $client->whatsapp_number,
            'status' => 'draft',
        ]);

        return $this->send($message, $by);
    }

    /** Sends the message and records the outcome on it. A failure is stored on the message, not thrown. */
    public function send(ClientMessage $message, ?User $by = null): ClientMessage
    {
        $project = $message->project;
        $tenant = Tenant::findOrFail($message->tenant_id);
        $settings = $tenant->workspaceSettings();

        try {
            if (blank($message->to_address)) {
                throw new \RuntimeException($message->channel === 'email' ? 'This client has no email address.' : 'This client has no WhatsApp number.');
            }

            if ($message->channel === 'email') {
                Mail::to($message->to_address)->send(new ClientUpdateMail(
                    subjectLine: (string) $message->subject,
                    body: $message->body,
                    senderName: $settings['sender_name'],
                    signature: $settings['email_signature'],
                    projectName: $project?->name,
                    replyToAddress: $tenant->contact_email,
                ));
                $providerId = null;
            } else {
                if (! $tenant->planAllows('whatsapp_chat')) {
                    throw new \RuntimeException('WhatsApp chat is not included in your plan.');
                }
                $providerId = $this->whatsapp->forTenant($tenant)->sendText($message->to_address, $message->body);
            }

            $message->forceFill(['status' => 'sent', 'sent_at' => now(), 'error' => null, 'provider_message_id' => $providerId])->save();
            ActivityLog::record('client.message.sent', $message, ['channel' => $message->channel, 'reason' => $message->reason], $by?->id);
        } catch (Throwable $e) {
            report($e);
            $message->forceFill(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 400)])->save();
        }

        return $message;
    }

    /** @return array<string, mixed> */
    public function facts(Project $project): array
    {
        $summary = $this->tracker->summary($project);

        return [
            'status' => $summary['status'],
            'progress' => $summary['progress_percent'],
            'due' => $summary['revised_due_date'] ?? $summary['original_due_date'],
            'overdue_working_days' => $summary['overdue_working_days'],
            'client_delay_days' => $summary['client_delay_days'],
            'pending_changes' => $summary['pending_requirements'],
            'late_items' => $project->clientDependencies
                ->filter(fn ($d) => $d->isPending() && $d->daysDelayed() > 0)
                ->map(fn ($d) => ['title' => $d->title, 'expected' => $d->expected_on?->toDateString(), 'days_late' => $d->daysDelayed()])
                ->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $facts
     * @return array{subject: string, body: string, ai: bool}
     */
    private function generate(Tenant $tenant, Project $project, string $channel, string $reason, ?string $instruction, array $facts): array
    {
        $settings = $tenant->workspaceSettings();
        $contact = $project->client?->contact_person ?: $project->client?->name ?: 'there';

        $system = "You write messages from a software delivery team to their client. Tone: {$settings['tone']}. "
            .'Use only the facts you are given and never invent dates, numbers or promises. '
            .($channel === 'whatsapp' ? 'WhatsApp: plain text, friendly, under 600 characters, no subject. ' : 'Email: a clear subject and a short body in plain paragraphs, no signature (it is added later). ')
            .'Reply with JSON only: {"subject": string, "body": string}.'
            .($settings['knowledge_base'] !== '' ? "\n\nCompany knowledge base (follow it):\n".Str::limit($settings['knowledge_base'], 6000, '') : '');

        $prompt = json_encode([
            'greet' => $contact,
            'sender' => $settings['sender_name'],
            'project' => $project->name,
            'reason' => $reason,
            'extra_instruction' => $reason === 'project_update'
                ? "Tell the client about this change in the project and mention they can follow it in their portal: {$instruction}"
                : $instruction,
            'facts' => $facts,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $data = $this->ai->completeJson($system, (string) $prompt);

        if ($data !== null && filled($data['body'] ?? null)) {
            return [
                'subject' => Str::limit(trim((string) ($data['subject'] ?? '')) ?: $this->fallbackSubject($project, $reason), 180, ''),
                'body' => trim((string) $data['body']),
                'ai' => true,
            ];
        }

        return ['subject' => $this->fallbackSubject($project, $reason), 'body' => $this->fallbackBody($project, $contact, $reason, $instruction, $facts), 'ai' => false];
    }

    private function fallbackSubject(Project $project, string $reason): string
    {
        return match ($reason) {
            'dependency_overdue' => "Items we are waiting on for {$project->name}",
            'status_alert' => "Status update on {$project->name}",
            'progress_update' => "Progress update for {$project->name}",
            'project_update' => "Update on {$project->name}",
            default => "About {$project->name}",
        };
    }

    /** @param array<string, mixed> $facts */
    private function fallbackBody(Project $project, string $contact, string $reason, ?string $instruction, array $facts): string
    {
        $status = ucwords(str_replace('_', ' ', (string) $facts['status']));
        $lines = ["Hello {$contact},", ''];

        if ($reason === 'dependency_overdue' && $facts['late_items'] !== []) {
            $lines[] = "To keep {$project->name} moving, we are still waiting on the following from your side:";
            foreach ($facts['late_items'] as $item) {
                $lines[] = "- {$item['title']} (expected {$item['expected']}, {$item['days_late']} days overdue)";
            }
            $lines[] = '';
            $lines[] = 'Could you please share these at the earliest? Delays here move the delivery date.';
        } elseif ($reason === 'project_update' && filled($instruction)) {
            $lines[] = "There is a change on {$project->name}:";
            $lines[] = "- {$instruction}";
            $lines[] = '';
            $lines[] = 'You can follow every step, the time spent and shared documents in your client portal.';
        } elseif ($reason === 'custom' && filled($instruction)) {
            $lines[] = (string) $instruction;
        } else {
            $lines[] = "Here is the latest on {$project->name}: it is currently {$status} at {$facts['progress']}% complete, with delivery planned for {$facts['due']}.";
            if (($facts['client_delay_days'] ?? 0) > 0) {
                $lines[] = "{$facts['client_delay_days']} days of delay are due to pending inputs from your side.";
            }
        }

        $lines[] = '';
        $lines[] = 'Please let us know if you have any questions.';

        return implode("\n", $lines);
    }
}
