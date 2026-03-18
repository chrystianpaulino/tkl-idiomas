<?php

namespace Database\Factories;

use App\Models\ExerciseList;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExerciseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'exercise_list_id' => ExerciseList::factory(),
            'order' => 1,
            'question' => $this->faker->sentence().'?',
            'type' => 'text',
        ];
    }

    public function fileType(): static
    {
        return $this->state(['type' => 'file']);
    }
}
