<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\LessonPackage;
use App\Models\School;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    public function definition(): array
    {
        $school = School::factory()->create();
        $professor = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);
        $student = User::factory()->create(['role' => 'aluno', 'school_id' => $school->id]);
        $class = TurmaClass::factory()->create(['professor_id' => $professor->id, 'school_id' => $school->id]);
        $package = LessonPackage::factory()->create(['student_id' => $student->id, 'school_id' => $school->id]);

        return [
            'school_id' => $school->id,
            'class_id' => $class->id,
            'student_id' => $student->id,
            'professor_id' => $professor->id,
            'package_id' => $package->id,
            'title' => $this->faker->sentence(4),
            'notes' => $this->faker->optional()->paragraph(),
            'conducted_at' => now(),
        ];
    }
}
