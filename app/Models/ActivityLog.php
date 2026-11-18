<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['tenant_id', 'user_id', 'subject_type', 'subject_id', 'event', 'properties', 'ip_address'])]
class ActivityLog extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Record an audit event for the current tenant and user.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function record(string $event, ?Model $subject = null, array $properties = [], ?int $userId = null): self
    {
        return static::create([
            'user_id' => $userId ?? auth()->id(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'event' => $event,
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => request()?->ip(),
        ]);
    }
}
