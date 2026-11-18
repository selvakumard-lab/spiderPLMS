<?php

namespace App\Http\Resources;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Plan */
class PlanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'price_monthly' => $this->price_monthly,
            'price_yearly' => $this->price_yearly,
            'currency' => $this->currency,
            'max_users' => $this->max_users,
            'max_projects' => $this->max_projects,
            'storage_gb' => $this->storage_gb,
            'whatsapp_chat' => (bool) $this->whatsapp_chat,
            'features' => $this->features ?? [],
        ];
    }
}
