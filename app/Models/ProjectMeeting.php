<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['project_id', 'created_by', 'meeting_date', 'title', 'details', 'discussion_points', 'links'])]
class ProjectMeeting extends Model
{
    use BelongsToTenant;

    protected $table = 'project_meetings';

    protected function casts(): array
    {
        return ['meeting_date' => 'date', 'links' => 'array'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<MeetingAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(MeetingAttachment::class);
    }
}
