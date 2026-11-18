<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'project_id', 'user_id', 'actor', 'actor_name', 'kind', 'event_type', 'title', 'body', 'occurred_at', 'subject_type', 'subject_id', 'payload', 'visible_to_client'])]
class ProjectEvent extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'payload' => 'array', 'visible_to_client' => 'boolean'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
