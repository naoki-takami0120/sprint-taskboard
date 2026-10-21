<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Sprint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sprint>
 */
class SprintFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 month', '+1 month');

        return [
            'project_id' => Project::factory(),
            'title' => 'Sprint '.fake()->numberBetween(1, 20),
            'start_date' => $start,
            'end_date' => (clone $start)->modify('+2 weeks'),
        ];
    }
}
