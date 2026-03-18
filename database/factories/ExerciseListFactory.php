<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExerciseListFactory extends Factory
{
    public function definition(): array
    {
        return [
            'class_id' => TurmaClass::factory(),
            'lesson_id' => null,
            'created_by' => User::factory()->state(['role' => 'professor']),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->optional()->paragraph(),
            'due_date' => $this->faker->optional()->dateTimeBetween('+1 day', '+30 days')?->format('Y-m-d'),
            'school_id' => School::factory(),
        ];
    }

    public function overdue(): static
    {
        return $this->state([
            'due_date' => now()->subDays(3)->format('Y-m-d'),
        ]);
    }

    public function noDueDate(): static
    {
        return $this->state([
            'due_date' => null,
        ]);
    }
}
