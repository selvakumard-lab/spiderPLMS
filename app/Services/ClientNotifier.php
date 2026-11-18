<?php

namespace App\Services;

use App\Models\ClientMessage;
use App\Models\Project;
use App\Models\ProjectChatMessage;
use App\Models\ProjectEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ClientProjectUpdate;
use Illuminate\Support\Facades\Notification;

/**
 * Two-way project notifications. Every change is recorded on the project timeline, then the other side is told:
 * team actions notify the client (portal bell plus a reviewed email draft); client actions notify the team.
 */
class ClientNotifier
{
    /** A team action: tell the client, in their portal, that something changed. */
    public function notify(Project $project, string $kind, string $title, ?string $body = null): void
    {
        $this->record($project, $kind, $title, $body);
        $this->alertClient($project, $kind, $title, $body);
        $this->queueEmailDraft($project, $title, $body);
    }

    /** A client action: tell every active team member of the workspace. */
    public function notifyTeam(Project $project, string $kind, string $title, ?string $body = null): void
    {
        $this->record($project, $kind, $title, $body);
        $this->alertTeam($project, $kind, $title, $body);
    }

    /**
     * Adds a message to the project's chat thread and alerts the other side. Chat is conversation, not a project change,
     * so it stays off the timeline and never queues an email draft.
     */
    public function postChatMessage(Project $project, User $author, string $body): ProjectChatMessage
    {
        $message = ProjectChatMessage::withoutGlobalScopes()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'user_id' => $author->id,
            'sender' => $author->isClient() ? 'client' : 'team',
            'sender_name' => $author->name,
            'body' => $body,
        ]);

        $title = "{$author->name} sent a message";
        $this->record($project, 'message', $title, $body);
        $author->isClient() ? $this->alertTeam($project, 'message', $title, $body) : $this->alertClient($project, 'message', $title, $body);

        return $message;
    }

    private function alertClient(Project $project, string $kind, string $title, ?string $body): void
    {
        if ($project->client_id === null) {
            return;
        }

        $users = User::withoutGlobalScopes()
            ->where('tenant_id', $project->tenant_id)->where('client_id', $project->client_id)
            ->where('status', 'active')->get();

        if ($users->isNotEmpty()) {
            Notification::send($users, new ClientProjectUpdate($project, $kind, $title, $body));
        }
    }

    private function alertTeam(Project $project, string $kind, string $title, ?string $body): void
    {
        $team = User::withoutGlobalScopes()
            ->where('tenant_id', $project->tenant_id)->whereNull('client_id')
            ->where('is_super_admin', false)->where('status', 'active')->get();

        if ($team->isNotEmpty()) {
            Notification::send($team, new ClientProjectUpdate($project, $kind, $title, $body));
        }
    }

    /** Adds a line to the project timeline; the person acting is the signed-in user. */
    public function record(Project $project, string $kind, string $title, ?string $body = null): ProjectEvent
    {
        $actor = auth()->user();

        return ProjectEvent::withoutGlobalScopes()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'user_id' => $actor?->id,
            'actor' => $actor?->isClient() ? 'client' : 'team',
            'actor_name' => $actor?->name ?? 'System',
            'kind' => $kind,
            'title' => $title,
            'body' => $body,
        ]);
    }

    /**
     * Prepares an email about the change for a person to review and send. Nothing is emailed automatically.
     * Changes that arrive while a draft is still waiting are added to that draft as extra lines.
     */
    private function queueEmailDraft(Project $project, string $title, ?string $body): void
    {
        $tenant = Tenant::find($project->tenant_id);
        $project->loadMissing('client');

        if ($tenant === null || ! $tenant->workspaceSettings()['email_updates'] || blank($project->client?->email)) {
            return;
        }

        $line = trim($title.($body ? ' '.$body : ''));
        $waiting = ClientMessage::where('project_id', $project->id)->where('channel', 'email')->where('reason', 'project_update')->where('status', 'draft')->latest('id')->first();

        if ($waiting !== null) {
            if (! str_contains($waiting->body, $title)) {
                $waiting->forceFill(['body' => rtrim($waiting->body)."\n- {$line}"])->save();
            }

            return;
        }

        $draft = function () use ($project, $line): void {
            app(ClientCommunicationService::class)->draft($project, 'email', 'project_update', $line);
        };

        // Written after the response so saving a step stays fast; the AI call can take a few seconds.
        app()->runningUnitTests() ? $draft() : dispatch($draft)->afterResponse();
    }
}
