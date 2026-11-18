<?php

namespace App\Support;

use App\Models\ChangeRequest;
use App\Models\ChatEvaluation;
use App\Models\Client;
use App\Models\ClientDependency;
use App\Models\ClientMessage;
use App\Models\MeetingAttachment;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectChatMessage;
use App\Models\ProjectDocument;
use App\Models\ProjectEvent;
use App\Models\ProjectMeeting;

/**
 * Array shapes returned by the tracking API (and reused by the reports).
 */
class TrackerPresenter
{
    /** @return array<string, mixed> */
    public static function project(Project $p): array
    {
        return [
            'id' => $p->id,
            'code' => $p->code,
            'name' => $p->name,
            'description' => $p->description,
            'client_id' => $p->client_id,
            'client_name' => $p->client_name,
            'status' => $p->status,
            'stage' => $p->stage,
            'stage_label' => $p->stageLabel(),
            'tracking_status' => $p->tracking_status,
            'progress' => (int) $p->progress,
            'start_date' => $p->start_date?->toDateString(),
            'due_date' => $p->due_date?->toDateString(),
            'revised_due_date' => $p->revised_due_date?->toDateString(),
            'total_working_days' => $p->total_working_days,
            'status_updated_at' => $p->status_updated_at?->toIso8601String(),
            'ai_summary' => $p->ai_summary,
            'meta' => $p->meta,
        ];
    }

    /** @return array<string, mixed> */
    public static function activity(ProjectActivity $a): array
    {
        return [
            'id' => $a->id,
            'activity_date' => $a->activity_date->toDateString(),
            'title' => $a->title,
            'description' => $a->description,
            'hours_spent' => $a->hours_spent,
            'days_spent' => $a->days_spent,
            'estimated_hours' => $a->estimated_hours,
            'next_step_estimate_hours' => $a->next_step_estimate_hours,
            'status' => $a->status,
            'progress_percent' => $a->progress_percent,
            'ai_analysis' => $a->ai_analysis,
        ];
    }

    /** @return array<string, mixed> */
    public static function meeting(ProjectMeeting $m): array
    {
        return [
            'id' => $m->id,
            'meeting_date' => $m->meeting_date->toDateString(),
            'title' => $m->title,
            'details' => $m->details,
            'discussion_points' => $m->discussion_points,
            'links' => $m->links ?? [],
            'attachments' => $m->attachments->map(fn (MeetingAttachment $f) => [
                'id' => $f->id, 'name' => $f->name, 'mime_type' => $f->mime_type, 'size' => $f->size,
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    public static function changeRequest(ChangeRequest $c): array
    {
        return [
            'id' => $c->id,
            'title' => $c->title,
            'description' => $c->description,
            'submitted_on' => $c->submitted_on->toDateString(),
            'status' => $c->status,
            'decided_on' => $c->decided_on?->toDateString(),
            'additional_days' => $c->additional_days,
            'additional_hours' => $c->additional_hours,
        ];
    }

    /** @return array<string, mixed> */
    public static function dependency(ClientDependency $d): array
    {
        return [
            'id' => $d->id,
            'title' => $d->title,
            'description' => $d->description,
            'requested_on' => $d->requested_on->toDateString(),
            'expected_on' => $d->expected_on?->toDateString(),
            'received_on' => $d->received_on?->toDateString(),
            'days_delayed' => $d->daysDelayed(),
            'is_pending' => $d->isPending(),
        ];
    }

    /** @return array<string, mixed> */
    public static function client(Client $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'contact_person' => $c->contact_person,
            'email' => $c->email,
            'phone' => $c->phone,
            'whatsapp_number' => $c->whatsapp_number,
            'notes' => $c->notes,
            'projects_count' => $c->projects_count ?? null,
            'has_portal_access' => (bool) ($c->has_portal_access ?? false),
        ];
    }

    /** @return array<string, mixed> */
    public static function chatMessage(ProjectChatMessage $m): array
    {
        return [
            'id' => $m->id,
            'sender' => $m->sender,
            'sender_name' => $m->sender_name,
            'user_id' => $m->user_id,
            'body' => $m->body,
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function event(ProjectEvent $e): array
    {
        return [
            'id' => $e->id,
            'actor' => $e->actor,
            'actor_name' => $e->actor_name,
            'kind' => $e->kind,
            'title' => $e->title,
            'body' => $e->body,
            'created_at' => $e->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function document(ProjectDocument $d): array
    {
        return [
            'id' => $d->id,
            'title' => $d->title,
            'note' => $d->note,
            'name' => $d->name,
            'mime_type' => $d->mime_type,
            'size' => $d->size,
            'visible_to_client' => $d->visible_to_client,
            'from_client' => $d->from_client,
            'created_at' => $d->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function message(ClientMessage $m): array
    {
        return [
            'id' => $m->id,
            'project_id' => $m->project_id,
            'project_name' => $m->relationLoaded('project') ? $m->project?->name : null,
            'client_name' => $m->relationLoaded('client') ? $m->client?->name : null,
            'channel' => $m->channel,
            'direction' => $m->direction,
            'reason' => $m->reason,
            'subject' => $m->subject,
            'body' => $m->body,
            'to_address' => $m->to_address,
            'status' => $m->status,
            'ai_generated' => $m->ai_generated,
            'error' => $m->error,
            'sent_at' => $m->sent_at?->toIso8601String(),
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function evaluation(ChatEvaluation $e): array
    {
        return [
            'id' => $e->id,
            'summary' => $e->summary,
            'sentiment' => $e->sentiment,
            'pending_items' => $e->pending_items ?? [],
            'suggested_reply' => $e->suggested_reply,
            'messages_considered' => $e->messages_considered,
            'source' => $e->source,
            'created_at' => $e->created_at?->toIso8601String(),
        ];
    }
}
