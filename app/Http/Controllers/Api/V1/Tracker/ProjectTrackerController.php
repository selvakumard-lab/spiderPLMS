<?php

namespace App\Http\Controllers\Api\V1\Tracker;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectAiAnalysis;
use App\Services\ClientMailer;
use App\Services\ClientNotifier;
use App\Services\DeepSeekService;
use App\Services\MilestonePlanner;
use App\Services\ProjectOnboardingService;
use App\Services\ProjectTrackerService;
use App\Support\Tenancy\TenantContext;
use App\Support\TrackerPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectTrackerController extends Controller
{
    public function __construct(private ProjectTrackerService $tracker, private ClientNotifier $notifier, private MilestonePlanner $planner, private ProjectOnboardingService $onboarding, private ClientMailer $mailer) {}

    public function index(Request $request): JsonResponse
    {
        $projects = Project::query()
            ->when($request->filled('search'), fn ($q) => $q->whereLike('name', '%'.$request->string('search').'%'))
            ->when($request->filled('status'), fn ($q) => $q->where('tracking_status', $request->string('status')))
            ->latest()->get();

        return response()->json(['data' => $projects->map(fn (Project $p) => TrackerPresenter::project($p))]);
    }

    /**
     * Dashboard figures: counts per status plus every project's progress.
     */
    public function dashboard(): JsonResponse
    {
        $projects = Project::latest()->get();

        return response()->json([
            'total' => $projects->count(),
            'by_status' => collect(ProjectTrackerService::STATUSES)->mapWithKeys(fn ($s) => [$s => $projects->where('tracking_status', $s)->count()]),
            'average_progress' => $projects->isEmpty() ? 0 : (int) round($projects->avg('progress')),
            'projects' => $projects->map(fn (Project $p) => TrackerPresenter::project($p)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(app(TenantContext::class)->get()->withinLimit('projects'), 402, 'Your plan project limit has been reached.');

        $plan = $request->validate([
            'analysis_id' => ['nullable', 'integer', Rule::exists('project_ai_analyses', 'id')->where('tenant_id', app(TenantContext::class)->id())->whereNull('project_id')],
            'steps' => ['nullable', 'array', 'max:12'],
            'steps.*.title' => ['required', 'string', 'max:190'],
            'steps.*.description' => ['nullable', 'string', 'max:2000'],
            'steps.*.stage' => ['nullable', Rule::in(array_keys(Project::STAGES))],
            'steps.*.working_days' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'steps.*.deliverables' => ['nullable', 'array', 'max:10'],
            'steps.*.deliverables.*' => ['string', 'max:200'],
        ]);
        $analysis = isset($plan['analysis_id']) ? ProjectAiAnalysis::find($plan['analysis_id']) : null;

        $project = Project::create($this->validated($request) + ['status_updated_at' => now(), 'status' => 'active']);

        $created = $this->onboarding->applyPlan($project, $plan['steps'] ?? [], $analysis, $request->user());
        if ($created === 0) {
            $this->planner->seedDefaults($project);
        }
        $this->tracker->refresh($project);

        $this->notifier->record($project, 'status', 'Project created', null, [
            'type' => 'project.created', 'subject' => $project, 'internal' => true,
            'payload' => ['start_date' => $project->start_date?->toDateString(), 'due_date' => $project->due_date?->toDateString(), 'working_days' => $project->total_working_days, 'steps' => $project->milestones()->count(), 'from_document' => $analysis !== null],
        ]);
        if ($analysis !== null) {
            $this->notifier->record($project, 'document', 'Project documentation analysed by AI', "Source: {$analysis->source}. Confidence {$analysis->confidence}%.", [
                'type' => 'ai.analysis_applied', 'subject' => $analysis, 'internal' => true,
                'payload' => ['source' => $analysis->source, 'confidence' => $analysis->confidence, 'estimated_days' => $analysis->estimate['working_days'] ?? null, 'file' => $analysis->original_name],
            ]);
        }

        $this->mailer->projectCreated($project, $request->user());

        return response()->json(['data' => TrackerPresenter::project($project->refresh())], 201);
    }

    public function show(Project $project): JsonResponse
    {
        $summary = $this->tracker->refresh($project);
        $project->load(['activities', 'meetings.attachments', 'changeRequests', 'clientDependencies', 'documents']);
        $events = $project->events()->limit(100)->get();

        return response()->json([
            'data' => TrackerPresenter::project($project),
            'summary' => $summary,
            'activities' => $project->activities->map(fn ($a) => TrackerPresenter::activity($a)),
            'meetings' => $project->meetings->map(fn ($m) => TrackerPresenter::meeting($m)),
            'change_requests' => $project->changeRequests->map(fn ($c) => TrackerPresenter::changeRequest($c)),
            'client_dependencies' => $project->clientDependencies->map(fn ($d) => TrackerPresenter::dependency($d)),
            'documents' => $project->documents->map(fn ($d) => TrackerPresenter::document($d)),
            'events' => $events->map(fn ($e) => TrackerPresenter::event($e)),
            'plan' => $this->planner->plan($project->milestones()->get()),
            'stages' => Project::stageOptions(),
        ]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $data = $this->validated($request, partial: true);
        $extra = $request->validate([
            'status' => ['sometimes', Rule::in(['active', 'on_hold', 'completed', 'cancelled'])],
            'stage' => ['sometimes', Rule::in(array_keys(Project::STAGES))],
        ]);

        $previousStage = $project->stage;
        $previousStatus = $project->status;
        $previousDue = $project->due_date?->toDateString();
        $project->update($data + $extra);

        if ($project->wasChanged('stage')) {
            $this->notifier->notify($project, 'stage', "Project moved to {$project->stageLabel()}", 'Previous stage: '.(Project::STAGES[$previousStage] ?? '-').'.', [
                'type' => 'project.stage_changed', 'subject' => $project, 'payload' => ['from' => $previousStage, 'to' => $project->stage],
            ]);
        }
        if ($project->wasChanged('status')) {
            $this->notifier->notify($project, 'status', 'Project marked as '.str_replace('_', ' ', $project->status), null, [
                'type' => 'project.status_changed', 'subject' => $project, 'payload' => ['from' => $previousStatus, 'to' => $project->status],
            ]);
        }
        if ($project->wasChanged('status') && $project->status === 'completed') {
            $this->mailer->projectCompleted($project, $request->user());
        }
        if ($project->wasChanged(['name', 'description', 'start_date', 'due_date', 'revised_due_date'])) {
            $this->notifier->notify($project, 'update', 'Project details updated', 'Delivery date: '.($project->revised_due_date ?? $project->due_date)?->format('j M Y').'.', [
                'type' => 'project.details_changed', 'subject' => $project,
                'payload' => ['changed' => array_keys($project->getChanges()), 'due_date' => ['from' => $previousDue, 'to' => $project->due_date?->toDateString()]],
            ]);
        }

        $this->tracker->refresh($project);

        return response()->json(['data' => TrackerPresenter::project($project->refresh())]);
    }

    public function destroy(Project $project): JsonResponse
    {
        $project->delete();

        return response()->json(['message' => 'Project deleted.']);
    }

    /**
     * Re-run the AI analysis over the whole project.
     */
    public function analyse(Project $project, DeepSeekService $ai): JsonResponse
    {
        $result = $ai->analyse($project);
        $project->forceFill(['ai_summary' => $result['summary']])->saveQuietly();
        $summary = $this->tracker->refresh($project);

        return response()->json(['data' => TrackerPresenter::project($project->refresh()), 'summary' => $summary, 'ai' => $result]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        $data = $request->validate([
            'name' => [$required, 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'client_name' => ['nullable', 'string', 'max:160'],
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->where('tenant_id', app(TenantContext::class)->id())->whereNull('deleted_at')],
            'start_date' => [$required, 'date'],
            'due_date' => [$required, 'date', 'after_or_equal:start_date'],
            'total_working_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'revised_due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'meta' => ['nullable', 'array'],
        ]);

        // Picking a client from the master list fills the display name when none was typed.
        if (! empty($data['client_id']) && blank($data['client_name'] ?? null)) {
            $data['client_name'] = Client::find($data['client_id'])?->name;
        }

        return $data;
    }
}
