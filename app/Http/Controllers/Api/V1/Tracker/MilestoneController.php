<?php

namespace App\Http\Controllers\Api\V1\Tracker;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Services\ClientNotifier;
use App\Services\MilestonePlanner;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The project plan: the steps from kickoff to delivery, each with its own deadline and actual dates.
 * Moving a step on tells the client and keeps the project stage in step with the work.
 */
class MilestoneController extends Controller
{
    public function __construct(private MilestonePlanner $planner, private ClientNotifier $notifier) {}

    /** Creates the default step list for a project that has none. */
    public function generate(Project $project): JsonResponse
    {
        abort_if($project->milestones()->exists(), 422, 'This project already has a step plan.');
        abort_if($project->start_date === null || $project->due_date === null, 422, 'Set the project start and due dates first.');

        $this->planner->seedDefaults($project);
        $this->notifier->notify($project, 'step', 'Project plan created', 'The steps from kickoff to delivery are now listed.', ['type' => 'plan.created', 'payload' => ['steps' => $project->milestones()->count(), 'source' => 'default']]);

        return $this->plan($project, 201);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $this->validated($request);
        $this->assertDatesInOrder($data['planned_start'] ?? null, $data['due_date'] ?? null);

        $milestone = $project->milestones()->create($data + [
            'tenant_id' => $project->tenant_id,
            'sort_order' => ((int) $project->milestones()->max('sort_order')) + 1,
            'status' => 'pending',
        ]);

        $this->notifier->notify($project, 'step', "New step planned: {$data['title']}", isset($data['due_date']) ? 'Deadline: '.date('j M Y', strtotime($data['due_date'])).'.' : null, [
            'type' => 'milestone.created', 'subject' => $milestone, 'payload' => ['due_date' => $data['due_date'] ?? null, 'assigned_to' => $data['assigned_to'] ?? null],
        ]);

        return $this->plan($project, 201);
    }

    public function update(Request $request, ProjectMilestone $milestone): JsonResponse
    {
        $data = $this->validated($request, partial: true);
        $previous = $milestone->status;
        $previousDue = $milestone->due_date?->toDateString();
        $previousOwner = $milestone->assigned_to;

        $milestone->fill($data);
        $this->assertDatesInOrder($milestone->planned_start?->toDateString(), $milestone->due_date?->toDateString());

        if ($milestone->isDirty('status')) {
            $this->applyStatusDates($milestone, $data);
        }
        if ($milestone->started_on !== null && $milestone->completed_on !== null && $milestone->completed_on->lt($milestone->started_on)) {
            throw ValidationException::withMessages(['completed_on' => 'A step cannot finish before it started.']);
        }

        $deadlineChanged = $milestone->isDirty('due_date');
        $milestone->save();
        $project = $milestone->project;

        if ($milestone->status !== $previous) {
            $this->announceStatus($project, $milestone);
        } elseif ($deadlineChanged && $milestone->due_date !== null) {
            $this->notifier->notify($project, 'step', "Deadline changed: {$milestone->title}", 'New deadline: '.$milestone->due_date->format('j M Y').'.', [
                'type' => 'milestone.deadline_changed', 'subject' => $milestone, 'payload' => ['from' => $previousDue, 'to' => $milestone->due_date->toDateString()],
            ]);
        }

        if ($milestone->assigned_to !== $previousOwner) {
            $owner = $milestone->assignee?->name;
            $this->notifier->record($project, 'step', $owner ? "{$milestone->title} assigned to {$owner}" : "{$milestone->title} is no longer assigned", null, [
                'type' => 'milestone.assigned', 'subject' => $milestone, 'internal' => true, 'payload' => ['from' => $previousOwner, 'to' => $milestone->assigned_to],
            ]);
        }

        return $this->plan($project);
    }

    public function destroy(ProjectMilestone $milestone): JsonResponse
    {
        $project = $milestone->project;
        $milestone->delete();

        return $this->plan($project);
    }

    private function plan(Project $project, int $status = 200): JsonResponse
    {
        return response()->json($this->planner->plan($project->milestones()->get()), $status);
    }

    /**
     * Starting stamps today as the start; finishing stamps today as the end; going back to pending clears both.
     *
     * @param  array<string, mixed>  $given
     */
    private function applyStatusDates(ProjectMilestone $milestone, array $given): void
    {
        $today = today();

        match ($milestone->status) {
            'pending' => $milestone->forceFill(['started_on' => null, 'completed_on' => null, 'started_at' => null, 'completed_at' => null]),
            'in_progress' => $milestone->forceFill(['started_on' => $milestone->started_on ?? $today, 'started_at' => $milestone->started_at ?? now(), 'completed_on' => null, 'completed_at' => null]),
            default => $milestone->forceFill([
                'started_on' => $milestone->started_on ?? $today,
                'started_at' => $milestone->started_at ?? now(),
                'completed_on' => $given['completed_on'] ?? $milestone->completed_on ?? $today,
                'completed_at' => now(),
            ]),
        };
    }

    private function announceStatus(Project $project, ProjectMilestone $milestone): void
    {
        $info = $this->planner->present($milestone);

        $payload = ['status' => $milestone->status, 'due_date' => $milestone->due_date?->toDateString(), 'days_late' => $info['days_late'], 'days_taken' => $info['days_taken'], 'estimated_days' => $milestone->estimated_days];
        $meta = fn (string $type) => ['type' => $type, 'subject' => $milestone, 'payload' => $payload];

        match ($milestone->status) {
            'in_progress' => $this->notifier->notify($project, 'step', "Step started: {$milestone->title}", $milestone->due_date ? 'Deadline: '.$milestone->due_date->format('j M Y').'.' : null, $meta('milestone.started')),
            'completed' => $this->notifier->notify($project, 'step', "Step completed: {$milestone->title}", $info['days_late'] > 0
                ? "Finished {$info['days_late']} working day".($info['days_late'] === 1 ? '' : 's').' after the deadline.'
                : 'Finished on time'.($info['days_taken'] ? " in {$info['days_taken']} working day".($info['days_taken'] === 1 ? '' : 's') : '').'.', $meta('milestone.completed')),
            default => $this->notifier->notify($project, 'step', "Step moved back to pending: {$milestone->title}", null, $meta('milestone.reset')),
        };

        // Keep the project stage in step with the work: the step being worked on decides the stage.
        if ($milestone->status === 'in_progress' && $milestone->stage !== null && $project->stage !== $milestone->stage) {
            $previous = $project->stage;
            $project->update(['stage' => $milestone->stage]);
            $this->notifier->notify($project, 'stage', "Project moved to {$project->stageLabel()}", 'Previous stage: '.(Project::STAGES[$previous] ?? '-').'.');
        }
    }

    private function assertDatesInOrder(?string $start, ?string $due): void
    {
        if ($start !== null && $due !== null && $due < $start) {
            throw ValidationException::withMessages(['due_date' => 'The deadline cannot be before the planned start.']);
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$required, 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'stage' => ['nullable', Rule::in(array_keys(Project::STAGES))],
            'planned_start' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'started_on' => ['nullable', 'date'],
            'completed_on' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(ProjectMilestone::STATUSES)],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', app(TenantContext::class)->id())->whereNull('client_id')->where('status', 'active')],
            'estimated_days' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'deliverables' => ['nullable', 'array', 'max:10'],
            'deliverables.*' => ['string', 'max:200'],
        ], ['title.required' => 'Give the step a short title.', 'assigned_to.exists' => 'Choose an active member of your team.']);
    }
}
