<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'job_title' => $this->job_title,
            'phone' => $this->phone,
            'status' => $this->status,
            'is_super_admin' => $this->isSuperAdmin(),
            'is_client' => $this->isClient(),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'project_role' => $this->whenPivotLoaded('project_members', fn () => $this->pivot->project_role),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
