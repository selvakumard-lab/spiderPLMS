<?php

namespace App\Console\Commands;

use App\Models\ClientMessage;
use App\Models\Project;
use App\Models\Tenant;
use App\Services\ClientCommunicationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Prepares reminder and alert DRAFTS for clients. Nothing is sent: a person reviews each draft first.
 */
#[Signature('plms:draft-reminders')]
#[Description('Draft client emails for overdue client inputs and delayed projects (drafts only, never sent)')]
class DraftClientReminders extends Command
{
    private const REPEAT_AFTER_DAYS = ['dependency_overdue' => 3, 'status_alert' => 7];

    public function handle(ClientCommunicationService $communication, TenantContext $context): int
    {
        $drafted = 0;

        Tenant::query()->whereIn('status', ['active', 'trial'])->get()->each(function (Tenant $tenant) use ($communication, $context, &$drafted): void {
            if (! $tenant->hasAccess() || ! $tenant->workspaceSettings()['auto_draft']) {
                return;
            }

            $context->runAs($tenant, function () use ($communication, &$drafted): void {
                Project::with(['client', 'clientDependencies'])->where('status', 'active')->get()->each(function (Project $project) use ($communication, &$drafted): void {
                    if (blank($project->client?->email)) {
                        return;
                    }

                    $overdue = $project->clientDependencies->contains(fn ($d) => $d->isPending() && $d->daysDelayed() > 0);

                    if ($overdue && $this->isDue($project, 'dependency_overdue')) {
                        $communication->draft($project, 'email', 'dependency_overdue');
                        $drafted++;
                    }

                    if (in_array($project->tracking_status, ['delayed', 'significantly_delayed'], true) && $this->isDue($project, 'status_alert')) {
                        $communication->draft($project, 'email', 'status_alert');
                        $drafted++;
                    }
                });
            });
        });

        $this->info("Drafted {$drafted} client message(s) for review.");

        return self::SUCCESS;
    }

    /** No new draft while one is waiting or one was handled recently. */
    private function isDue(Project $project, string $reason): bool
    {
        return ! ClientMessage::where('project_id', $project->id)->where('reason', $reason)->where('channel', 'email')
            ->where(fn ($q) => $q->where('status', 'draft')->orWhere('created_at', '>=', now()->subDays(self::REPEAT_AFTER_DAYS[$reason])))
            ->exists();
    }
}
