<?php

namespace App\Services;

use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Builds the two project reports as neutral "sections" and renders them to PDF or Excel.
 *
 * Section shape: ['heading' => string, 'columns' => string[]|null, 'rows' => list<list<scalar|null>>]
 * Without columns, rows are plain label/value pairs.
 */
class ProjectReportService
{
    public const STATUS_LABELS = [
        'on_time' => 'On Time', 'slightly_delayed' => 'Slightly Delayed', 'delayed' => 'Delayed',
        'significantly_delayed' => 'Significantly Delayed', 'completed' => 'Completed',
    ];

    public function __construct(private ProjectTrackerService $tracker) {}

    /**
     * @return array{title: string, subtitle: string, sections: list<array<string, mixed>>}
     */
    public function build(Project $project, string $type): array
    {
        $summary = $this->tracker->refresh($project);
        $project->load(['activities', 'changeRequests', 'clientDependencies', 'meetings']);

        return $type === 'progress'
            ? $this->progressReport($project, $summary)
            : $this->performanceReport($project, $summary);
    }

    /**
     * @param  array<string, mixed>  $s
     * @return array{title: string, subtitle: string, sections: list<array<string, mixed>>}
     */
    private function progressReport(Project $project, array $s): array
    {
        return [
            'title' => 'Project Progress Report',
            'subtitle' => $project->name.' ('.$project->code.')',
            'sections' => [
                ['heading' => 'Project overview', 'columns' => null, 'rows' => [
                    ['Project', $project->name],
                    ['Client', $project->client_name ?: '-'],
                    ['Description', $project->description ?: '-'],
                    ['Overall status', self::STATUS_LABELS[$s['status']]],
                    ['Progress', $s['progress_percent'].'%'],
                    ['Status last updated', $project->status_updated_at?->format('d M Y H:i')],
                ]],
                ['heading' => 'Activity-wise progress', 'columns' => ['Date', 'Activity', 'Description', 'Status', 'Progress %', 'Estimated (h)', 'Actual (h)', 'Days', 'Next step est. (h)'],
                    'rows' => $project->activities->map(fn ($a) => [
                        $a->activity_date->format('d M Y'), $a->title, $a->description, ucfirst(str_replace('_', ' ', $a->status)),
                        $a->progress_percent, $a->estimated_hours, $a->hours_spent, $a->days_spent, $a->next_step_estimate_hours,
                    ])->all()],
                ['heading' => 'Estimated vs. actual time', 'columns' => null, 'rows' => [
                    ['Estimated hours (activities with an estimate)', $s['estimated_hours']],
                    ['Actual hours on those activities', $s['actual_hours_on_estimated']],
                    ['Total hours spent', $s['hours_spent']],
                    ['Total days spent', $s['days_spent']],
                ]],
                ['heading' => 'AI-based progress analysis', 'columns' => null, 'rows' => [['Analysis', $project->ai_summary ?: 'No analysis yet.']]],
                ['heading' => 'Overall project status', 'columns' => null, 'rows' => [
                    ['Completed activities', $s['completed_activities']],
                    ['Pending activities', $s['pending_activities']],
                    ['Client-side pending items', $s['client_pending_items']],
                    ['Remaining working days', $s['remaining_working_days']],
                ]],
                ['heading' => 'Original and revised timeline', 'columns' => null, 'rows' => [
                    ['Original start date', $s['original_start_date']],
                    ['Original completion date', $s['original_due_date']],
                    ['Revised completion date', $s['revised_due_date'] ?? 'Not revised'],
                    ['Total working days', $s['total_working_days']],
                ]],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $s
     * @return array{title: string, subtitle: string, sections: list<array<string, mixed>>}
     */
    private function performanceReport(Project $project, array $s): array
    {
        $start = $project->start_date;
        $end = $project->status === 'completed' ? $project->activities->max('activity_date') : now();

        return [
            'title' => 'Project Performance & Delay Analysis Report',
            'subtitle' => $project->name.' ('.$project->code.')',
            'sections' => [
                ['heading' => 'Duration and delays', 'columns' => null, 'rows' => [
                    ['Overall status', self::STATUS_LABELS[$s['status']]],
                    ['Total project duration (calendar days so far)', $start ? (int) $start->diffInDays($end) : '-'],
                    ['Working days spent', $s['days_spent']],
                    ['Internal work delay (working days)', $s['internal_delay_days']],
                    ['Client-side delay (days)', $s['client_delay_days']],
                    ['Overdue working days past deadline', $s['overdue_working_days']],
                ]],
                ['heading' => 'Client dependencies', 'columns' => ['Required from client', 'Requested', 'Expected', 'Received', 'Days delayed', 'Status'],
                    'rows' => $project->clientDependencies->map(fn ($d) => [
                        $d->title, $d->requested_on->format('d M Y'), $d->expected_on?->format('d M Y'), $d->received_on?->format('d M Y'),
                        $d->daysDelayed(), $d->isPending() ? 'Pending' : 'Received',
                    ])->all()],
                ['heading' => 'Additional client requirements', 'columns' => ['Requirement', 'Submitted', 'Status', 'Decided', 'Extra days', 'Extra hours'],
                    'rows' => $project->changeRequests->map(fn ($c) => [
                        $c->title, $c->submitted_on->format('d M Y'), ucfirst($c->status), $c->decided_on?->format('d M Y'), $c->additional_days, $c->additional_hours,
                    ])->all()],
                ['heading' => 'Pending approvals and additional work', 'columns' => null, 'rows' => [
                    ['Requirements awaiting approval', $s['pending_requirements']],
                    ['Approved additional working days', $s['additional_days_approved']],
                    ['Time on additional (approved) work, hours', $s['additional_work_hours']],
                ]],
                ['heading' => 'Deadline changes', 'columns' => null, 'rows' => [
                    ['Original completion date', $s['original_due_date']],
                    ['Revised completion date', $s['revised_due_date'] ?? 'Not revised'],
                    ['Days added to deadline (working)', $s['additional_days_approved']],
                ]],
                ['heading' => 'Overall performance analysis', 'columns' => null, 'rows' => [
                    ['Progress', $s['progress_percent'].'%'],
                    ['Likely cause of delay', $this->cause($s)],
                    ['AI analysis', $project->ai_summary ?: 'No analysis yet.'],
                ]],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $s
     */
    private function cause(array $s): string
    {
        if ($s['status'] === 'completed') {
            return 'Project completed.';
        }
        if ($s['internal_delay_days'] === 0 && $s['client_delay_days'] === 0) {
            return 'No delay recorded.';
        }

        return $s['client_delay_days'] >= $s['internal_delay_days'] ? 'Mainly client-side (pending inputs).' : 'Mainly internal work.';
    }

    public function pdf(Project $project, string $type): Response
    {
        $report = $this->build($project, $type);

        return Pdf::loadView('reports.project', ['report' => $report])
            ->setPaper('a4', 'landscape')
            ->download($this->filename($project, $type, 'pdf'));
    }

    public function excel(Project $project, string $type): Response
    {
        $report = $this->build($project, $type);
        $path = tempnam(sys_get_temp_dir(), 'plms');

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Report');

        $writer->addRow(Row::fromValues([$report['title']]));
        $writer->addRow(Row::fromValues([$report['subtitle']]));

        foreach ($report['sections'] as $section) {
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues([strtoupper($section['heading'])]));

            if ($section['columns'] !== null) {
                $writer->addRow(Row::fromValues($section['columns']));
            }
            foreach ($section['rows'] as $row) {
                $writer->addRow(Row::fromValues(array_map(fn ($v) => $v ?? '', $row)));
            }
        }
        $writer->close();

        return response()->download($path, $this->filename($project, $type, 'xlsx'))->deleteFileAfterSend();
    }

    private function filename(Project $project, string $type, string $ext): string
    {
        return sprintf('%s-%s-report.%s', $project->code, $type, $ext);
    }
}
