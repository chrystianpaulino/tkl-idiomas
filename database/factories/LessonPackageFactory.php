<?php

namespace Database\Factories;

use App\Models\LessonPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LessonPackageFactory extends Factory
{
    protected $model = LessonPackage::class;

    public function definition(): array
    {
        return [
            'student_id' => User::factory()->state(['role' => 'aluno']),
            'total_lessons' => $this->faker->numberBetween(5, 40),
            'purchased_at' => now(),
            'expires_at' => $this->faker->optional()->dateTimeBetween('+1 month', '+1 year'),
            'school_id' => \App\Models\School::factory(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function exhausted(): static
    {
        return $this->afterCreating(function (LessonPackage $package) {
            $package->used_lessons = $package->total_lessons;
            $package->save();
        });
    }
}
