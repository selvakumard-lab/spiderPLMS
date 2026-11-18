<?php

namespace App\Http\Controllers\Api\V1\Tracker;

use App\Http\Controllers\Controller;
use App\Models\ClientDependency;
use App\Models\Project;
use App\Services\ClientNotifier;
use App\Services\ProjectTrackerService;
use App\Support\TrackerPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Information, documents or approvals the project is waiting on from the client.
 */
class ClientDependencyController extends Controller
{
    public function __construct(private ProjectTrackerService $tracker, private ClientNotifier $notifier) {}

    public function store(Request $request, Project $project): JsonResponse
    {
        $dependency = $project->clientDependencies()->create($this->validated($request) + ['created_by' => $request->user()->id]);
        $this->notifier->notify($project, 'request', "We need something from you: {$dependency->title}", $dependency->expected_on ? 'Needed by '.$dependency->expected_on->format('j M Y').'.' : null);

        return $this->respond($project, $dependency, 201);
    }

    public function update(Request $request, ClientDependency $dependency): JsonResponse
    {
        $dependency->update($this->validated($request, partial: true));

        return $this->respond($dependency->project, $dependency);
    }

    public function destroy(ClientDependency $dependency): JsonResponse
    {
        $project = $dependency->project;
        $dependency->delete();

        return response()->json(['summary' => $this->tracker->refresh($project)]);
    }

    private function respond(Project $project, ClientDependency $dependency, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => TrackerPresenter::dependency($dependency->refresh()),
            'summary' => $this->tracker->refresh($project),
        ], $status);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$required, 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'requested_on' => [$required, 'date'],
            'expected_on' => ['nullable', 'date'],
            'received_on' => ['nullable', 'date'],
        ]);
    }
}
