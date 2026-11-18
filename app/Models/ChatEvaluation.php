<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id', 'project_id', 'client_id', 'summary', 'sentiment', 'pending_items', 'suggested_reply', 'messages_considered', 'source'])]
class ChatEvaluation extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['pending_items' => 'array'];
    }
}
