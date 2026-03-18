<?php

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\ScheduledLesson;
use App\Models\School;
use App\Models\TurmaClass;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduledLessonFactory extends Factory
{
    protected $model = ScheduledLesson::class;

    public function definition(): array
    {
        return [
            'schedule_id' => Schedule::factory(),
            'class_id' => TurmaClass::factory(),
            'scheduled_at' => $this->faker->dateTimeBetween('now', '+30 days'),
            'status' => 'scheduled',
            'cancelled_reason' => null,
            'lesson_id' => null,
            'school_id' => School::factory(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['status' => 'confirmed']);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => 'cancelled',
            'cancelled_reason' => $this->faker->sentence(),
        ]);
    }
}
