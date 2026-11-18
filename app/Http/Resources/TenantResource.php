<?php

namespace App\Http\Resources;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tenant */
class TenantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'url' => url('/'.$this->slug),
            'custom_domain' => $this->custom_domain,
            'status' => $this->status->value,
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'country_code' => $this->country_code,
            'contact_email' => $this->contact_email,
            'phone' => $this->phone,
            'address' => $this->address,
            'tax_id' => $this->tax_id,
            'settings' => collect($this->settings ?? [])->except('workspace')->all() ?: (object) [],
            'admin_notes' => $this->when($request->user()?->isSuperAdmin(), $this->admin_notes),
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'users_count' => $this->whenCounted('users'),
            'projects_count' => $this->whenCounted('projects'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
