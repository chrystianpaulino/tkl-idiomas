<?php

namespace Database\Factories;

use App\Models\ExerciseList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExerciseSubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'exercise_list_id' => ExerciseList::factory(),
            'student_id' => User::factory()->state(['role' => 'aluno']),
            'submitted_at' => null,
            'completed' => false,
        ];
    }

    public function submitted(): static
    {
        return $this->state([
            'submitted_at' => now(),
            'completed' => true,
        ]);
    }
}
