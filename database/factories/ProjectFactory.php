<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-3 months', '+2 weeks');

        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement(['Customer Portal', 'Mobile Banking App', 'ERP Integration', 'Marketing Website', 'Data Warehouse', 'HR Self-Service', 'Inventory System', 'Analytics Platform']).' '.fake()->randomElement(['v2', 'Revamp', 'Phase II', '2027', 'MVP']),
            'description' => fake()->paragraph(),
            'status' => 'active',
            'start_date' => $start,
            'due_date' => fake()->dateTimeBetween($start, '+6 months'),
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn () => ['tenant_id' => $tenant->id]);
    }
}
