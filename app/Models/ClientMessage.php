<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'project_id', 'client_id', 'created_by', 'channel', 'direction', 'reason', 'subject', 'body', 'to_address', 'status', 'ai_generated', 'provider_message_id', 'error', 'sent_at', 'read_at'])]
class ClientMessage extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['ai_generated' => 'boolean', 'sent_at' => 'datetime', 'read_at' => 'datetime'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
