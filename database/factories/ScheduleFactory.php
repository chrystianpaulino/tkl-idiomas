<?php

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\School;
use App\Models\TurmaClass;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    public function definition(): array
    {
        return [
            'class_id' => TurmaClass::factory(),
            'weekday' => $this->faker->numberBetween(0, 6),
            'start_time' => $this->faker->time('H:i'),
            'duration_minutes' => 60,
            'active' => true,
            'school_id' => School::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }

    /**
     * Bypass mass-assignment guards when seeding test data.
     *
     * Schedule::$fillable intentionally excludes class_id and school_id.
     * Factories need to populate those fields, so we forceFill all attributes
     * regardless of $fillable.
     */
    public function newModel(array $attributes = []): Schedule
    {
        $model = new Schedule;
        $model->forceFill($attributes);

        return $model;
    }
}
