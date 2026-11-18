<?php

namespace App\Services;

use App\Mail\PortalAccessMail;
use App\Mail\ProjectCompletedMail;
use App\Mail\ProjectCreatedMail;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The emails a client always gets without anyone reviewing them first: portal access, a new project and a
 * delivered project. A failure to send never blocks the action that caused it; it is logged and the team can resend.
 * (Progress updates and reminders are different: those are drafted for a person to review.)
 */
class ClientMailer
{
    public function __construct(private ClientNotifier $notifier) {}

    public function portalAccessGranted(Client $client, User $portalUser, ?User $by = null): bool
    {
        $tenant = Tenant::findOrFail($client->tenant_id);
        $settings = $tenant->workspaceSettings();

        $sent = $this->deliver($portalUser->email, new PortalAccessMail(
            clientName: $client->contact_person ?: $client->name,
            workspaceName: $tenant->name,
            signInUrl: url('/'.$tenant->slug),
            email: $portalUser->email,
            senderName: $settings['sender_name'],
            appName: (string) SystemSetting::get('app_name'),
            supportEmail: $tenant->contact_email ?: (string) SystemSetting::get('support_email'),
            replyToAddress: $tenant->contact_email,
        ));

        ActivityLog::record($sent ? 'client.portal.invited' : 'client.portal.invite_failed', $client, ['email' => $portalUser->email], $by?->id);

        return $sent;
    }

    public function projectCreated(Project $project, ?User $by = null): bool
    {
        $project->loadMissing('client', 'milestones');
        $client = $project->client;

        if ($client === null || blank($client->email)) {
            return false;
        }

        $tenant = Tenant::findOrFail($project->tenant_id);
        $steps = $project->milestones->sortBy('sort_order')->values();

        $sent = $this->deliver($client->email, new ProjectCreatedMail(
            clientName: $client->contact_person ?: $client->name,
            workspaceName: $tenant->name,
            projectName: $project->name,
            projectCode: $project->code,
            startDate: $project->start_date->format('j M Y'),
            dueDate: ($project->revised_due_date ?? $project->due_date)->format('j M Y'),
            workingDays: $project->total_working_days,
            steps: $steps->take(6)->map(fn ($m) => ['title' => $m->title, 'due' => $m->due_date?->format('j M')])->all(),
            stepCount: $steps->count(),
            portalUrl: $this->portalUrl($client, $tenant),
            senderName: $tenant->workspaceSettings()['sender_name'],
            appName: (string) SystemSetting::get('app_name'),
            supportEmail: $tenant->contact_email ?: (string) SystemSetting::get('support_email'),
            replyToAddress: $tenant->contact_email,
        ));

        $this->record($project, 'email.project_created', $sent, $client->email, 'Project start email sent to the client');

        return $sent;
    }

    public function projectCompleted(Project $project, ?User $by = null): bool
    {
        $project->loadMissing('client', 'milestones');
        $client = $project->client;

        if ($client === null || blank($client->email)) {
            return false;
        }

        $tenant = Tenant::findOrFail($project->tenant_id);
        $planned = ($project->revised_due_date ?? $project->due_date);
        $delivered = today();
        $difference = $planned === null ? 0 : (int) $planned->copy()->startOfDay()->diffInDays($delivered, false);
        $steps = $project->milestones;

        $sent = $this->deliver($client->email, new ProjectCompletedMail(
            clientName: $client->contact_person ?: $client->name,
            workspaceName: $tenant->name,
            projectName: $project->name,
            projectCode: $project->code,
            startedOn: $project->start_date->format('j M Y'),
            deliveredOn: $delivered->format('j M Y'),
            plannedOn: ($planned ?? $delivered)->format('j M Y'),
            calendarDays: max(1, (int) $project->start_date->copy()->startOfDay()->diffInDays($delivered) + 1),
            verdict: $difference > 0 ? 'late' : ($difference < 0 ? 'early' : 'on_time'),
            daysDifference: abs($difference),
            stepsDone: $steps->where('status', 'completed')->count(),
            stepsTotal: $steps->count(),
            portalUrl: $this->portalUrl($client, $tenant),
            senderName: $tenant->workspaceSettings()['sender_name'],
            appName: (string) SystemSetting::get('app_name'),
            supportEmail: $tenant->contact_email ?: (string) SystemSetting::get('support_email'),
            replyToAddress: $tenant->contact_email,
        ));

        $this->record($project, 'email.project_completed', $sent, $client->email, 'Project delivered email sent to the client');

        return $sent;
    }

    /** The sign-in page, only when the client can actually sign in. */
    private function portalUrl(Client $client, Tenant $tenant): ?string
    {
        return $client->portalUsers()->exists() ? url('/'.$tenant->slug) : null;
    }

    private function deliver(string $to, Mailable $mail): bool
    {
        try {
            Mail::to($to)->send($mail);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    private function record(Project $project, string $type, bool $sent, string $to, string $title): void
    {
        $this->notifier->record($project, 'update', $sent ? $title : 'Email to the client could not be sent', $sent ? "Sent to {$to}." : "Delivery to {$to} failed. Check the mail settings and send an update by hand.", [
            'type' => $sent ? $type : $type.'_failed', 'internal' => true, 'subject' => $project, 'payload' => ['to' => $to, 'sent' => $sent],
        ]);
    }
}
