<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_id', 'created_by', 'activity_date', 'title', 'description', 'hours_spent', 'days_spent', 'estimated_hours', 'next_step_estimate_hours', 'status', 'progress_percent', 'ai_analysis'])]
class ProjectActivity extends Model
{
    use BelongsToTenant;

    protected $table = 'project_activities';

    protected function casts(): array
    {
        return ['activity_date' => 'date', 'hours_spent' => 'float', 'days_spent' => 'float', 'estimated_hours' => 'float', 'next_step_estimate_hours' => 'float'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
