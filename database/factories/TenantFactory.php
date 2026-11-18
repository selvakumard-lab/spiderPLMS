<?php

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'status' => TenantStatus::Active,
            'trial_ends_at' => null,
            'timezone' => 'UTC',
            'currency' => 'USD',
        ];
    }

    public function trial(): static
    {
        return $this->state(fn () => ['status' => TenantStatus::Trial, 'trial_ends_at' => now()->addDays(14)]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => TenantStatus::Suspended]);
    }
}
