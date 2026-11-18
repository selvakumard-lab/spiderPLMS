<?php

namespace App\Http\Controllers\Api\V1\Tracker;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Services\ClientNotifier;
use App\Services\DeepSeekService;
use App\Services\ProjectTrackerService;
use App\Support\TrackerPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Step-by-step work log. Each save re-runs the AI estimate and refreshes the project status.
 */
class ActivityController extends Controller
{
    public function __construct(private ProjectTrackerService $tracker, private DeepSeekService $ai, private ClientNotifier $notifier) {}

    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $this->validated($request);
        $activity = $project->activities()->create($data + ['created_by' => $request->user()->id]);
        $this->notifier->notify($project, 'step', "New step: {$activity->title}", 'Status: '.str_replace('_', ' ', $activity->status).". Overall progress {$activity->progress_percent}%.");

        return $this->respond($project, $activity, 201);
    }

    public function update(Request $request, ProjectActivity $activity): JsonResponse
    {
        $activity->update($this->validated($request, partial: true));

        if ($activity->wasChanged(['status', 'progress_percent'])) {
            $this->notifier->notify($activity->project, 'step', "Step updated: {$activity->title}", 'Status: '.str_replace('_', ' ', $activity->status).". Overall progress {$activity->progress_percent}%.");
        }

        return $this->respond($activity->project, $activity);
    }

    public function destroy(ProjectActivity $activity): JsonResponse
    {
        $project = $activity->project;
        $activity->delete();

        return response()->json(['summary' => $this->tracker->refresh($project)]);
    }

    private function respond(Project $project, ProjectActivity $activity, int $status = 200): JsonResponse
    {
        $project->unsetRelation('activities');
        $result = $this->ai->analyse($project);

        $activity->forceFill([
            'next_step_estimate_hours' => $result['next_step_hours'],
            'ai_analysis' => $result['summary'],
        ])->save();
        $project->forceFill(['ai_summary' => $result['summary']])->saveQuietly();

        return response()->json([
            'data' => TrackerPresenter::activity($activity->refresh()),
            'summary' => $this->tracker->refresh($project),
            'ai_source' => $result['source'],
        ], $status);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        $data = $request->validate([
            'activity_date' => [$required, 'date'],
            'title' => [$required, 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'hours_spent' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'days_spent' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'status' => ['sometimes', Rule::in(['pending', 'in_progress', 'completed'])],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        // Days default to 8-hour working days when only hours are entered.
        if (isset($data['hours_spent']) && ! isset($data['days_spent'])) {
            $data['days_spent'] = round($data['hours_spent'] / 8, 2);
        }

        return $data;
    }
}
