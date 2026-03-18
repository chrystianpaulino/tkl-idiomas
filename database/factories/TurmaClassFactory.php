<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TurmaClassFactory extends Factory
{
    protected $model = TurmaClass::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'professor_id' => User::factory()->professor(),
            'description' => $this->faker->optional()->sentence(),
            'school_id' => School::factory(),
        ];
    }
}
