<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\Tenant;
use App\Services\ChatEvaluationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Re-evaluates a project's WhatsApp chat after a new client message arrives.
 */
class EvaluateClientChat
{
    use Dispatchable;

    public function __construct(public int $projectId) {}

    public function handle(ChatEvaluationService $evaluator, TenantContext $context): void
    {
        $project = Project::withoutGlobalScopes()->find($this->projectId);
        $tenant = $project ? Tenant::find($project->tenant_id) : null;

        if ($project === null || $tenant === null) {
            return;
        }

        $context->runAs($tenant, fn () => $evaluator->evaluate($project));
    }
}
