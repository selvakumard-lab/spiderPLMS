<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_id', 'created_by', 'title', 'description', 'requested_on', 'expected_on', 'received_on'])]
class ClientDependency extends Model
{
    use BelongsToTenant;

    protected $table = 'client_dependencies';

    protected function casts(): array
    {
        return ['requested_on' => 'date', 'expected_on' => 'date', 'received_on' => 'date'];
    }

    /**
     * Days the client is late: against the actual receipt date, or today while still pending.
     */
    public function daysDelayed(): int
    {
        if ($this->expected_on === null) {
            return 0;
        }

        $until = $this->received_on ?? now()->startOfDay();

        return max(0, (int) $this->expected_on->diffInDays($until, false));
    }

    public function isPending(): bool
    {
        return $this->received_on === null;
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
