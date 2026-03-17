<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaterialFactory extends Factory
{
    protected $model = Material::class;

    public function definition(): array
    {
        return [
            'class_id' => \App\Models\TurmaClass::factory(),
            'uploaded_by' => User::factory()->professor(),
            'title' => $this->faker->sentence(3),
            'file_path' => 'materials/' . $this->faker->uuid() . '.pdf',
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
