<?php

namespace App\Http\Controllers\Api\V1\Tracker;

use App\Http\Controllers\Controller;
use App\Models\ProjectAiAnalysis;
use App\Services\ProjectOnboardingService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI project onboarding: read an uploaded requirement document and propose the project details, the working days
 * and the steps. Nothing is created here; the user reviews the proposal and creates the project from the normal form.
 */
class ProjectOnboardingController extends Controller
{
    public function __construct(private ProjectOnboardingService $onboarding) {}

    public function analyse(Request $request): JsonResponse
    {
        abort_unless(app(TenantContext::class)->get()->withinLimit('projects'), 402, 'Your plan project limit has been reached.');

        $request->validate(
            ['file' => ['required', 'file', 'max:10240']],
            ['file.required' => 'Choose a project document to upload.', 'file.max' => 'That file is larger than 10 MB.', 'file.uploaded' => 'The upload failed. The file may be larger than the server allows.'],
        );

        $analysis = $this->onboarding->analyse($request->file('file'), $request->user());

        return response()->json(['data' => $this->present($analysis)], 201);
    }

    /** @return array<string, mixed> */
    private function present(ProjectAiAnalysis $a): array
    {
        return [
            'id' => $a->id,
            'file' => $a->original_name,
            'source' => $a->source,
            'confidence' => $a->confidence,
            'extracted' => $a->extracted,
            'estimate' => $a->estimate,
        ];
    }
}
