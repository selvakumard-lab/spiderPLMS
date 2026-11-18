<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProjectMilestoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'project_id', 'stage', 'title', 'description', 'sort_order', 'planned_start', 'due_date', 'started_on', 'completed_on', 'status'])]
class ProjectMilestone extends Model
{
    /** @use HasFactory<ProjectMilestoneFactory> */
    use BelongsToTenant, HasFactory;

    public const STATUSES = ['pending', 'in_progress', 'completed'];

    protected function casts(): array
    {
        return ['planned_start' => 'date', 'due_date' => 'date', 'started_on' => 'date', 'completed_on' => 'date'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
