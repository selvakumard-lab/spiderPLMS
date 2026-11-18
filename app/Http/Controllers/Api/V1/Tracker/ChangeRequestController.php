<?php

namespace App\Http\Controllers\Api\V1\Tracker;

use App\Http\Controllers\Controller;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Services\ClientNotifier;
use App\Services\ProjectTrackerService;
use App\Support\TrackerPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * New client requirements. Approving one with extra working days moves the revised deadline.
 */
class ChangeRequestController extends Controller
{
    public function __construct(private ProjectTrackerService $tracker, private ClientNotifier $notifier) {}

    public function store(Request $request, Project $project): JsonResponse
    {
        $change = $project->changeRequests()->create($this->validated($request) + ['created_by' => $request->user()->id]);

        return $this->respond($project, $change, 201);
    }

    public function update(Request $request, ChangeRequest $changeRequest): JsonResponse
    {
        $changeRequest->update($this->validated($request, partial: true));

        if ($changeRequest->wasChanged('status')) {
            $this->notifier->notify($changeRequest->project, 'requirement', "Requirement {$changeRequest->status}: {$changeRequest->title}", $changeRequest->additional_days > 0 ? "{$changeRequest->additional_days} extra working days added to the deadline." : null);
        }

        return $this->respond($changeRequest->project, $changeRequest);
    }

    public function destroy(ChangeRequest $changeRequest): JsonResponse
    {
        $project = $changeRequest->project;
        $changeRequest->delete();
        $this->tracker->refreshRevisedDeadline($project);

        return response()->json(['summary' => $this->tracker->refresh($project)]);
    }

    private function respond(Project $project, ChangeRequest $change, int $status = 200): JsonResponse
    {
        $this->tracker->refreshRevisedDeadline($project);

        return response()->json([
            'data' => TrackerPresenter::changeRequest($change->refresh()),
            'summary' => $this->tracker->refresh($project),
        ], $status);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        $data = $request->validate([
            'title' => [$required, 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'submitted_on' => [$required, 'date'],
            'status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected'])],
            'decided_on' => ['nullable', 'date'],
            'additional_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'additional_hours' => ['nullable', 'numeric', 'min:0', 'max:99999'],
        ]);

        // Approving or rejecting stamps the decision date; reopening clears it.
        if (isset($data['status'])) {
            $data['decided_on'] = $data['status'] === 'pending' ? null : ($data['decided_on'] ?? now()->toDateString());
        }

        return $data;
    }
}
