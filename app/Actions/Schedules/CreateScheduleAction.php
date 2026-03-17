<?php

namespace App\Actions\Schedules;

use App\Models\Schedule;
use App\Models\TurmaClass;

/**
 * Creates a new recurring weekly schedule for a class.
 *
 * The schedule defines when a class meets (weekday + start_time + duration).
 * Actual lesson slots are materialized separately by GenerateScheduledLessonsAction.
 * New schedules are created as active by default.
 *
 * @see GenerateScheduledLessonsAction For materializing concrete lesson slots from this schedule
 */
class CreateScheduleAction
{
    /**
     * @param TurmaClass $turmaClass The class to create a schedule for
     * @param array      $data       Validated data: weekday (0-6), start_time (HH:MM), duration_minutes (optional, default 60)
     * @return Schedule              The newly created active schedule
     */
    public function execute(TurmaClass $turmaClass, array $data): Schedule
    {
        return Schedule::create([
            'class_id'         => $turmaClass->id,
            'weekday'          => $data['weekday'],
            'start_time'       => $data['start_time'],
            'duration_minutes' => $data['duration_minutes'] ?? 60,
            'active'           => true,
        ]);
    }
}
