<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\LessonPackage;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    public function definition(): array
    {
        $student = User::factory()->create(['role' => 'aluno']);
        $package = LessonPackage::factory()->create(['student_id' => $student->id]);

        return [
            'class_id' => TurmaClass::factory(),
            'student_id' => $student->id,
            'professor_id' => User::factory()->state(['role' => 'professor']),
            'package_id' => $package->id,
            'title' => $this->faker->sentence(4),
            'notes' => $this->faker->optional()->paragraph(),
            'conducted_at' => now(),
        ];
    }
}
