<?php

namespace App\Models;

use App\Enums\TenantStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'custom_domain', 'plan_id', 'status', 'trial_ends_at', 'logo_path', 'timezone', 'currency', 'country_code', 'contact_email', 'phone', 'address', 'tax_id', 'admin_notes', 'settings', 'onboarded_at'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'trial',
        'timezone' => 'UTC',
        'currency' => 'USD',
    ];

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant): void {
            $tenant->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
            'onboarded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return HasOne<Subscription, $this> */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    /**
     * Workspace preferences (knowledge base text, email tone, automation) kept inside the settings column.
     *
     * @return array{knowledge_base: string, tone: string, sender_name: string, email_signature: string, auto_draft: bool, email_updates: bool}
     */
    public function workspaceSettings(): array
    {
        $stored = (array) ($this->settings['workspace'] ?? []);

        return [
            'knowledge_base' => (string) ($stored['knowledge_base'] ?? ''),
            'tone' => (string) ($stored['tone'] ?? 'professional'),
            'sender_name' => (string) ($stored['sender_name'] ?? $this->name),
            'email_signature' => (string) ($stored['email_signature'] ?? ''),
            'auto_draft' => (bool) ($stored['auto_draft'] ?? true),
            'email_updates' => (bool) ($stored['email_updates'] ?? true),
        ];
    }

    /** @param array<string, mixed> $values */
    public function saveWorkspaceSettings(array $values): void
    {
        $settings = (array) $this->settings;
        $settings['workspace'] = [...$this->workspaceSettings(), ...$values];
        $this->forceFill(['settings' => $settings])->save();
    }

    public function isOnTrial(): bool
    {
        return $this->status === TenantStatus::Trial && (bool) $this->trial_ends_at?->isFuture();
    }

    /**
     * Whether members of this tenant may currently use the application.
     */
    public function hasAccess(): bool
    {
        if ($this->status === TenantStatus::Trial) {
            return $this->isOnTrial();
        }

        return $this->status->allowsAccess();
    }

    /** Whether the purchased plan includes a capability, such as `whatsapp_chat`. */
    public function planAllows(string $feature): bool
    {
        return (bool) $this->plan?->{$feature};
    }

    /** The Evolution API instance that belongs to this workspace (one linked phone per workspace). */
    public function whatsappInstance(): string
    {
        return config('services.evolution.instance').'-'.$this->id;
    }

    /**
     * Whether the plan allows one more record of the given kind ("users" or "projects").
     */
    public function withinLimit(string $resource): bool
    {
        $limit = match ($resource) {
            'users' => $this->plan?->max_users,
            'projects' => $this->plan?->max_projects,
            default => null,
        };

        if ($limit === null) {
            return true;
        }

        $count = match ($resource) {
            'users' => User::withoutGlobalScopes()->where('tenant_id', $this->id)->count(),
            'projects' => Project::withoutGlobalScopes()->where('tenant_id', $this->id)->count(),
        };

        return $count < $limit;
    }
}
