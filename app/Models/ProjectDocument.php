<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_id', 'uploaded_by', 'title', 'note', 'name', 'path', 'mime_type', 'size', 'visible_to_client', 'from_client'])]
class ProjectDocument extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['visible_to_client' => 'boolean', 'from_client' => 'boolean'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
