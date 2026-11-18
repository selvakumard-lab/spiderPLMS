<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClientDependency;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectEvent;
use App\Services\ClientNotifier;
use App\Services\DocumentStorage;
use App\Services\MilestonePlanner;
use App\Services\ProjectTrackerService;
use App\Support\TrackerPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What a client sees and can do after signing in: only their own projects, with status, stage, workflow,
 * time, documents and the shared timeline. Clients can upload documents, send updates and raise requirements.
 */
class PortalController extends Controller
{
    public function __construct(private ProjectTrackerService $tracker, private ClientNotifier $notifier, private DocumentStorage $storage, private MilestonePlanner $planner) {}

    public function projects(Request $request): JsonResponse
    {
        $projects = $this->ownedProjects($request)->latest()->get();

        return response()->json(['data' => $projects->map(fn (Project $p) => $this->projectCard($p))]);
    }

    public function project(Request $request, int $project): JsonResponse
    {
        $project = $this->ownedProjects($request)->findOrFail($project);
        $project->load(['activities', 'meetings', 'changeRequests', 'clientDependencies', 'documents' => fn ($q) => $q->where('visible_to_client', true)]);
        $summary = $this->tracker->summary($project);

        return response()->json([
            'data' => $this->projectCard($project),
            'stages' => Project::stageOptions(),
            'summary' => [
                'status' => $summary['status'],
                'progress_percent' => $summary['progress_percent'],
                'hours_spent' => $summary['hours_spent'],
                'days_spent' => $summary['days_spent'],
                'estimated_hours' => $summary['estimated_hours'],
                'additional_work_hours' => $summary['additional_work_hours'],
                'total_working_days' => $summary['total_working_days'],
                'remaining_working_days' => $summary['remaining_working_days'],
                'overdue_working_days' => $summary['overdue_working_days'],
                'completed_activities' => $summary['completed_activities'],
                'pending_activities' => $summary['pending_activities'],
                'additional_days_approved' => $summary['additional_days_approved'],
            ],
            'steps' => $project->activities->values()->map(fn ($a, $i) => [
                'number' => $i + 1,
                'id' => $a->id,
                'date' => $a->activity_date->toDateString(),
                'title' => $a->title,
                'description' => $a->description,
                'status' => $a->status,
                'hours_spent' => $a->hours_spent,
                'days_spent' => $a->days_spent,
                'estimated_hours' => $a->estimated_hours,
                'progress_percent' => $a->progress_percent,
            ]),
            'plan' => $this->planner->plan($project->milestones()->get()),
            'client_requests' => $project->clientDependencies->map(fn ($d) => TrackerPresenter::dependency($d)),
            'requirements' => $project->changeRequests->map(fn ($c) => TrackerPresenter::changeRequest($c)),
            'meetings' => $project->meetings->map(fn ($m) => [
                'id' => $m->id, 'date' => $m->meeting_date->toDateString(), 'title' => $m->title, 'discussion_points' => $m->discussion_points,
            ]),
            'documents' => $project->documents->map(fn (ProjectDocument $d) => TrackerPresenter::document($d)),
            'events' => ProjectEvent::where('project_id', $project->id)->latest('id')->limit(100)->get()->map(fn (ProjectEvent $e) => TrackerPresenter::event($e)),
        ]);
    }

    /** The client uploads a document the team needs. */
    public function uploadDocument(Request $request, int $project): JsonResponse
    {
        $project = $this->ownedProjects($request)->findOrFail($project);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'note' => ['nullable', 'string', 'max:2000'],
            'file' => ['required', 'file', 'max:20480'],
        ], DocumentStorage::MESSAGES);

        $document = $this->storage->store($project, $request->file('file'), $data['title'], $data['note'] ?? null, true, true, $request->user()->id);
        $this->notifier->notifyTeam($project, 'document', "{$request->user()->name} uploaded a document: {$document->title}", $document->note);

        return response()->json(['data' => TrackerPresenter::document($document)], 201);
    }

    /** The client sends the team an update, optionally about one of the things the team asked for. */
    public function postUpdate(Request $request, int $project): JsonResponse
    {
        $project = $this->ownedProjects($request)->findOrFail($project);

        $data = $request->validate([
            'message' => ['required', 'string', 'min:3', 'max:2000'],
            'request_id' => ['nullable', 'integer'],
        ], [
            'message.required' => 'Write your update first.',
            'message.min' => 'Your update is too short.',
            'message.max' => 'Your update is longer than 2000 characters.',
        ]);

        $about = isset($data['request_id']) ? ClientDependency::where('project_id', $project->id)->findOrFail($data['request_id']) : null;
        $title = $about ? "{$request->user()->name} replied about: {$about->title}" : "{$request->user()->name} sent an update";

        $this->notifier->notifyTeam($project, 'update', $title, $data['message']);

        return response()->json(['message' => 'Update sent to the team.'], 201);
    }

    /** The conversation with the team, oldest first. */
    public function chat(Request $request, int $project): JsonResponse
    {
        $project = $this->ownedProjects($request)->findOrFail($project);

        return response()->json(['data' => $project->chatMessages()->get()->map(fn ($m) => TrackerPresenter::chatMessage($m))]);
    }

    /** The client writes to the team in the project's chat thread. */
    public function sendChatMessage(Request $request, int $project): JsonResponse
    {
        $project = $this->ownedProjects($request)->findOrFail($project);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ], ['body.required' => 'Write a message first.', 'body.max' => 'Your message is longer than 2000 characters.']);

        $message = $this->notifier->postChatMessage($project, $request->user(), $data['body']);

        return response()->json(['data' => TrackerPresenter::chatMessage($message)], 201);
    }

    /** The client raises a new requirement. It waits for the team's decision. */
    public function raiseRequirement(Request $request, int $project): JsonResponse
    {
        $project = $this->ownedProjects($request)->findOrFail($project);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
        ], ['title.required' => 'Give the requirement a short title.']);

        $change = $project->changeRequests()->create($data + ['submitted_on' => now()->toDateString(), 'created_by' => $request->user()->id]);
        $this->tracker->refresh($project);
        $this->notifier->notifyTeam($project, 'requirement', "{$request->user()->name} raised a new requirement: {$change->title}", $change->description);

        return response()->json(['data' => TrackerPresenter::changeRequest($change->refresh())], 201);
    }

    public function downloadDocument(Request $request, int $document): StreamedResponse
    {
        $document = ProjectDocument::where('visible_to_client', true)
            ->whereIn('project_id', $this->ownedProjects($request)->select('id'))
            ->findOrFail($document);

        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->name);
    }

    /** @return Builder<Project> */
    private function ownedProjects(Request $request): Builder
    {
        return Project::query()->where('client_id', $request->user()->client_id);
    }

    /** @return array<string, mixed> */
    private function projectCard(Project $p): array
    {
        return [
            'id' => $p->id,
            'code' => $p->code,
            'name' => $p->name,
            'description' => $p->description,
            'status' => $p->status,
            'stage' => $p->stage,
            'stage_label' => $p->stageLabel(),
            'tracking_status' => $p->tracking_status,
            'progress' => (int) $p->progress,
            'start_date' => $p->start_date?->toDateString(),
            'due_date' => $p->due_date?->toDateString(),
            'revised_due_date' => $p->revised_due_date?->toDateString(),
            'status_updated_at' => $p->status_updated_at?->toIso8601String(),
        ];
    }
}
