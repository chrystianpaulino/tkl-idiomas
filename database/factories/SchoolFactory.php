<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class SchoolFactory extends Factory
{
    protected $model = School::class;

    public function definition(): array
    {
        return [
            'name'   => fake()->company(),
            'slug'   => fake()->unique()->lexify('school-????'),
            'email'  => fake()->optional()->companyEmail(),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
