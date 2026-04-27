<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\School;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaterialFactory extends Factory
{
    protected $model = Material::class;

    public function definition(): array
    {
        return [
            'class_id' => TurmaClass::factory(),
            'uploaded_by' => User::factory()->professor(),
            'title' => $this->faker->sentence(3),
            'file_path' => 'materials/'.$this->faker->uuid().'.pdf',
            'description' => $this->faker->optional()->sentence(),
            'school_id' => School::factory(),
        ];
    }

    /**
     * Bypass mass-assignment guards when seeding test data.
     *
     * Material::$fillable intentionally excludes class_id, uploaded_by, and
     * school_id. Factories need to populate those fields, so we forceFill all
     * attributes regardless of $fillable.
     */
    public function newModel(array $attributes = []): Material
    {
        $model = new Material;
        $model->forceFill($attributes);

        return $model;
    }
}
