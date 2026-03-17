<?php

namespace Database\Factories;

use App\Models\Exercise;
use App\Models\ExerciseSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExerciseAnswerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'exercise_submission_id' => ExerciseSubmission::factory(),
            'exercise_id' => Exercise::factory(),
            'answer_text' => $this->faker->paragraph(),
            'file_path' => null,
        ];
    }
}
