<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_id', 'created_by', 'title', 'description', 'submitted_on', 'status', 'decided_on', 'additional_days', 'additional_hours'])]
class ChangeRequest extends Model
{
    use BelongsToTenant;

    protected $table = 'change_requests';

    protected function casts(): array
    {
        return ['submitted_on' => 'date', 'decided_on' => 'date', 'additional_hours' => 'float'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
