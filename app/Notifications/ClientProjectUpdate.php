<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Notifications\Notification;

/**
 * A web notification for a client's portal: shows in their bell and notification list.
 */
class ClientProjectUpdate extends Notification
{
    public function __construct(public Project $project, public string $kind, public string $title, public ?string $body = null) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'project_id' => $this->project->id,
            'project_name' => $this->project->name,
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
        ];
    }
}
