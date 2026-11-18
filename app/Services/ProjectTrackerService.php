<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Carbon;

/**
 * Single source of truth for a project's timeline, delay attribution and status.
 * All figures derive from recorded activities, change requests and client dependencies.
 */
class ProjectTrackerService
{
    public const STATUSES = ['on_time', 'slightly_delayed', 'delayed', 'significantly_delayed', 'completed'];

    /**
     * Add working days (Mon–Fri) to a date.
     */
    public static function addWorkingDays(Carbon $date, int $days): Carbon
    {
        $result = $date->copy();

        for ($left = max(0, $days); $left > 0;) {
            $result->addDay();
            if ($result->isWeekday()) {
                $left--;
            }
        }

        return $result;
    }

    /**
     * Recalculate the revised deadline: original deadline plus the working days of approved change requests.
     */
    public function refreshRevisedDeadline(Project $project): void
    {
        $extra = (int) $project->changeRequests()->where('status', 'approved')->sum('additional_days');

        if ($project->due_date === null) {
            return;
        }

        $project->forceFill([
            'revised_due_date' => $extra > 0 ? self::addWorkingDays($project->due_date->copy(), $extra) : null,
        ])->saveQuietly();
    }

    /**
     * Recompute and persist the project's progress and tracking status.
     *
     * @return array<string, mixed>
     */
    public function refresh(Project $project): array
    {
        $project->refresh();
        $summary = $this->summary($project);
        $previous = $project->tracking_status;

        $project->forceFill([
            'progress' => $summary['progress_percent'],
            'tracking_status' => $summary['status'],
            'status_updated_at' => now(),
        ])->saveQuietly();

        if ($previous !== $summary['status']) {
            app(ClientNotifier::class)->notify($project, 'status', 'Project status: '.ucfirst(str_replace('_', ' ', $summary['status'])), "{$project->name} is now {$summary['progress_percent']}% complete.");
        }

        return $summary + ['status_updated_at' => $project->status_updated_at?->toIso8601String()];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Project $project): array
    {
        $project->load(['activities', 'changeRequests', 'clientDependencies']);
        $today = now()->startOfDay();

        $activities = $project->activities;
        $completed = $activities->where('status', 'completed')->count();

        $isCompleted = $project->status === 'completed';
        $latest = $activities->sortBy([['activity_date', 'asc'], ['id', 'asc']])->last();
        $progress = $isCompleted ? 100 : (int) ($latest?->progress_percent ?? 0);

        $effectiveDue = $project->revised_due_date ?? $project->due_date;
        $start = $project->start_date;

        $remainingDays = $effectiveDue === null || $isCompleted ? 0 : $this->workingDaysBetween($today, $effectiveDue);
        $overdueDays = $effectiveDue !== null && ! $isCompleted && $today->gt($effectiveDue) ? $this->workingDaysBetween($effectiveDue, $today) : 0;

        $gap = 0.0;
        $planned = 0;
        if ($start !== null && $effectiveDue !== null && $effectiveDue->gt($start)) {
            $planned = max(1, $this->workingDaysBetween($start, $effectiveDue));
            $elapsed = $today->lt($start) ? 0 : min($planned, $this->workingDaysBetween($start, $today));
            $gap = ($elapsed / $planned * 100) - $progress;
        }

        $status = match (true) {
            $isCompleted => 'completed',
            $overdueDays > 10 || $gap > 30 => 'significantly_delayed',
            $overdueDays > 0 || $gap > 15 => 'delayed',
            $gap > 5 => 'slightly_delayed',
            default => 'on_time',
        };

        $clientDelayDays = (int) $project->clientDependencies->sum(fn ($d) => $d->daysDelayed());
        $behindDays = $isCompleted ? 0 : (int) max($overdueDays, round(max(0, $gap) / 100 * $planned));
        $estimated = $activities->whereNotNull('estimated_hours');
        $approved = $project->changeRequests->where('status', 'approved');

        return [
            'status' => $status,
            'progress_percent' => $progress,
            'original_start_date' => $start?->toDateString(),
            'original_due_date' => $project->due_date?->toDateString(),
            'revised_due_date' => $project->revised_due_date?->toDateString(),
            'total_working_days' => $project->total_working_days,
            'additional_days_approved' => (int) $approved->sum('additional_days'),
            'days_spent' => round((float) $activities->sum('days_spent'), 2),
            'hours_spent' => round((float) $activities->sum('hours_spent'), 2),
            'remaining_working_days' => $remainingDays,
            'overdue_working_days' => $overdueDays,
            'completed_activities' => $completed,
            'pending_activities' => $activities->count() - $completed,
            'client_pending_items' => $project->clientDependencies->filter->isPending()->count(),
            'pending_requirements' => $project->changeRequests->where('status', 'pending')->count(),
            'estimated_hours' => round((float) $estimated->sum('estimated_hours'), 2),
            'actual_hours_on_estimated' => round((float) $estimated->sum('hours_spent'), 2),
            'client_delay_days' => $clientDelayDays,
            'internal_delay_days' => max(0, $behindDays - $clientDelayDays),
            'additional_work_hours' => round((float) $approved->sum('additional_hours'), 2),
        ];
    }

    public function workingDaysBetween(Carbon $from, Carbon $to): int
    {
        $count = 0;

        for ($day = $from->copy()->addDay(); $day->lte($to); $day->addDay()) {
            if ($day->isWeekday()) {
                $count++;
            }
        }

        return $count;
    }
}
