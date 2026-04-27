<?php

namespace Database\Factories;

use App\Models\ExerciseList;
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

    /**
     * Bypass mass-assignment guards when seeding test data.
     *
     * ExerciseList::$fillable intentionally excludes class_id, lesson_id,
     * created_by, and school_id. Factories need to populate those fields,
     * so we forceFill all attributes regardless of $fillable.
     */
    public function newModel(array $attributes = []): ExerciseList
    {
        $model = new ExerciseList;
        $model->forceFill($attributes);

        return $model;
    }
}
