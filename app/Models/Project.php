<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['tenant_id', 'code', 'name', 'description', 'client_id', 'status', 'start_date', 'due_date', 'total_working_days', 'revised_due_date', 'stage', 'tracking_status', 'status_updated_at', 'ai_summary', 'client_name', 'progress', 'meta'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    /** The steps a project moves through, in order. */
    public const STAGES = [
        'requirements' => 'Requirements',
        'design' => 'Design',
        'development' => 'Development',
        'testing' => 'Testing',
        'deployment' => 'Deployment',
        'handover' => 'Handover',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'active',
        'stage' => 'requirements',
        'progress' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (Project $project): void {
            if (empty($project->code)) {
                $next = static::withoutGlobalScopes()->withTrashed()->where('tenant_id', $project->tenant_id)->count() + 1;
                $project->code = sprintf('PRJ-%04d', $next);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'revised_due_date' => 'date',
            'status_updated_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /** @return list<array{value: string, label: string}> */
    public static function stageOptions(): array
    {
        return collect(self::STAGES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->stage] ?? ucfirst((string) $this->stage);
    }

    /** @return HasMany<ProjectMilestone, $this> */
    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<ProjectChatMessage, $this> */
    public function chatMessages(): HasMany
    {
        return $this->hasMany(ProjectChatMessage::class)->oldest('id');
    }

    /** @return HasMany<ProjectEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ProjectEvent::class)->latest('id');
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return HasMany<ClientMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ClientMessage::class);
    }

    /** @return HasMany<ProjectActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(ProjectActivity::class)->orderBy('activity_date')->orderBy('id');
    }

    /** @return HasMany<ProjectMeeting, $this> */
    public function meetings(): HasMany
    {
        return $this->hasMany(ProjectMeeting::class)->orderByDesc('meeting_date');
    }

    /** @return HasMany<ChangeRequest, $this> */
    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class)->orderBy('submitted_on');
    }

    /** @return HasMany<ProjectDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class)->latest();
    }

    /** @return HasMany<ClientDependency, $this> */
    public function clientDependencies(): HasMany
    {
        return $this->hasMany(ClientDependency::class)->orderBy('requested_on');
    }
}
