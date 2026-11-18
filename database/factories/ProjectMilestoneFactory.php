<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectMilestone>
 */
class ProjectMilestoneFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->randomElement(['Gather requirements', 'Design screens', 'Build the API', 'Test with the client']),
            'status' => 'pending',
            'sort_order' => 0,
            'planned_start' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(['status' => 'in_progress', 'started_on' => now()->toDateString()]);
    }

    public function completed(): static
    {
        return $this->state(['status' => 'completed', 'started_on' => now()->subDays(3)->toDateString(), 'completed_on' => now()->toDateString()]);
    }
}
