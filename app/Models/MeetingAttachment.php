<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_meeting_id', 'name', 'path', 'mime_type', 'size'])]
class MeetingAttachment extends Model
{
    use BelongsToTenant;

    /** @return BelongsTo<ProjectMeeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(ProjectMeeting::class, 'project_meeting_id');
    }
}
