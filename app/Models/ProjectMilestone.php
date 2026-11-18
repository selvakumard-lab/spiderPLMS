<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProjectMilestoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'project_id', 'stage', 'assigned_to', 'title', 'description', 'deliverables', 'sort_order', 'planned_start', 'due_date', 'estimated_days', 'started_on', 'completed_on', 'started_at', 'completed_at', 'status', 'source'])]
class ProjectMilestone extends Model
{
    /** @use HasFactory<ProjectMilestoneFactory> */
    use BelongsToTenant, HasFactory;

    public const STATUSES = ['pending', 'in_progress', 'completed'];

    protected function casts(): array
    {
        return [
            'planned_start' => 'date', 'due_date' => 'date', 'started_on' => 'date', 'completed_on' => 'date',
            'started_at' => 'datetime', 'completed_at' => 'datetime', 'deliverables' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
