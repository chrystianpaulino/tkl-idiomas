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
}
