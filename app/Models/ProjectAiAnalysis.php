<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What the AI read from an uploaded project document: extracted facts plus its initial estimate and suggested steps. */
#[Fillable(['tenant_id', 'project_id', 'created_by', 'original_name', 'path', 'mime_type', 'size', 'text_length', 'source', 'confidence', 'extracted', 'estimate'])]
class ProjectAiAnalysis extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['extracted' => 'array', 'estimate' => 'array'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
