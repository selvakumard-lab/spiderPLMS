<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectChatMessage>
 */
class ProjectChatMessageFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'sender' => 'team',
            'sender_name' => fake()->name(),
            'body' => fake()->sentence(),
        ];
    }

    public function fromClient(): static
    {
        return $this->state(['sender' => 'client']);
    }
}
