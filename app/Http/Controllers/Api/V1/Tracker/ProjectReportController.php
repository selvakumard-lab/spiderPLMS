<?php

namespace App\Http\Controllers\Api\V1\Tracker;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ProjectReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ProjectReportController extends Controller
{
    /**
     * Download report "progress" or "performance" as PDF or Excel.
     */
    public function download(Request $request, Project $project, string $type, ProjectReportService $reports): Response
    {
        $data = $request->validate(['format' => ['required', Rule::in(['pdf', 'xlsx'])]]);
        abort_unless(in_array($type, ['progress', 'performance'], true), 404);

        return $data['format'] === 'pdf' ? $reports->pdf($project, $type) : $reports->excel($project, $type);
    }
}
