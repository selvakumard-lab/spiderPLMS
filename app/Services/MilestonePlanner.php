<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The project plan: creates the default step list for a new project and works out, for every step, how long it
 * took, whether it is on time or delayed, and what comes next.
 */
class MilestonePlanner
{
    private const STAGE_DESCRIPTIONS = [
        'requirements' => 'Gather and confirm what the client needs.',
        'design' => 'Design the solution and get the client approval.',
        'development' => 'Build the agreed features.',
        'testing' => 'Test the work and fix issues with the client.',
        'deployment' => 'Release the work to its live environment.',
        'handover' => 'Hand over, train the client and close the project.',
    ];

    public function __construct(private ProjectTrackerService $tracker) {}

    /**
     * Creates one step per project stage, spreading the project's working days evenly across them.
     * Does nothing when the project already has a plan or has no dates. Returns how many steps were created.
     */
    public function seedDefaults(Project $project): int
    {
        if ($project->start_date === null || $project->due_date === null || $project->milestones()->exists()) {
            return 0;
        }

        $end = $project->revised_due_date ?? $project->due_date;
        $days = [];
        for ($day = $project->start_date->copy(); $day->lte($end); $day->addDay()) {
            if ($day->isWeekday()) {
                $days[] = $day->copy();
            }
        }
        $days = $days ?: [$project->start_date->copy()];

        $stages = array_keys(Project::STAGES);
        $total = count($days);
        $count = count($stages);

        foreach ($stages as $i => $stage) {
            $from = intdiv($i * $total, $count);
            $to = min($total - 1, max($from, intdiv(($i + 1) * $total, $count) - 1));
            $first = $i === 0 && $project->start_date->lte(today());

            $project->milestones()->create([
                'tenant_id' => $project->tenant_id,
                'stage' => $stage,
                'title' => Project::STAGES[$stage],
                'description' => self::STAGE_DESCRIPTIONS[$stage],
                'sort_order' => $i,
                'planned_start' => $days[$from]->toDateString(),
                'due_date' => $days[$to]->toDateString(),
                'status' => $first ? 'in_progress' : 'pending',
                'started_on' => $first ? $project->start_date->toDateString() : null,
            ]);
        }

        return $count;
    }

    /**
     * Everything the screens need about one step: stored fields plus time taken, lateness and a display status.
     *
     * @return array<string, mixed>
     */
    public function present(ProjectMilestone $m): array
    {
        $today = Carbon::today();
        $done = $m->status === 'completed';
        $overdue = ! $done && $m->due_date !== null && $today->gt($m->due_date);
        $end = $done ? ($m->completed_on ?? $today) : $today;
        $late = $m->due_date !== null && ($done ? $end->gt($m->due_date) : $overdue);

        $display = match (true) {
            $done => 'completed',
            $overdue => 'delayed',
            default => $m->status,
        };

        $timing = match (true) {
            $done && $late => 'completed_late',
            $done => 'completed_on_time',
            $overdue => 'overdue',
            $m->status === 'in_progress' => 'in_progress',
            default => 'upcoming',
        };

        return [
            'id' => $m->id,
            'number' => null,
            'stage' => $m->stage,
            'stage_label' => $m->stage ? (Project::STAGES[$m->stage] ?? null) : null,
            'title' => $m->title,
            'description' => $m->description,
            'sort_order' => $m->sort_order,
            'status' => $m->status,
            'display_status' => $display,
            'timing' => $timing,
            'planned_start' => $m->planned_start?->toDateString(),
            'due_date' => $m->due_date?->toDateString(),
            'started_on' => $m->started_on?->toDateString(),
            'completed_on' => $m->completed_on?->toDateString(),
            'planned_days' => $m->planned_start !== null && $m->due_date !== null ? $this->inclusiveDays($m->planned_start, $m->due_date) : null,
            'days_taken' => $m->started_on !== null ? max(1, $this->inclusiveDays($m->started_on, $end)) : null,
            'days_late' => $late ? max(1, $this->tracker->workingDaysBetween($m->due_date, $end)) : 0,
            'days_until_due' => ! $done && ! $overdue && $m->due_date !== null ? $this->tracker->workingDaysBetween($today, $m->due_date) : null,
        ];
    }

    /**
     * The plan as the screens show it: ordered, numbered steps and a summary of where the project is.
     *
     * @param  Collection<int, ProjectMilestone>  $milestones
     * @return array{milestones: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    public function plan(Collection $milestones): array
    {
        $rows = $milestones->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values()
            ->map(fn (ProjectMilestone $m, int $i) => ['number' => $i + 1] + $this->present($m))
            ->all();

        $total = count($rows);
        $completed = count(array_filter($rows, fn ($r) => $r['display_status'] === 'completed'));
        $next = collect($rows)->first(fn ($r) => $r['display_status'] !== 'completed');

        return [
            'milestones' => $rows,
            'summary' => [
                'total' => $total,
                'completed' => $completed,
                'in_progress' => count(array_filter($rows, fn ($r) => $r['display_status'] === 'in_progress')),
                'delayed' => count(array_filter($rows, fn ($r) => $r['display_status'] === 'delayed' || $r['timing'] === 'completed_late')),
                'overdue' => count(array_filter($rows, fn ($r) => $r['display_status'] === 'delayed')),
                'percent' => $total === 0 ? 0 : (int) round($completed / $total * 100),
                'next' => $next ? ['id' => $next['id'], 'title' => $next['title'], 'due_date' => $next['due_date'], 'display_status' => $next['display_status']] : null,
            ],
        ];
    }

    /** Working days from `$from` to `$to`, counting both ends. */
    private function inclusiveDays(Carbon $from, Carbon $to): int
    {
        return $to->lt($from) ? 0 : $this->tracker->workingDaysBetween($from->copy()->subDay(), $to);
    }
}
