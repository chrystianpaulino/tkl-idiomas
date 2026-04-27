<?php

namespace App\Actions\Schedules;

use App\Models\Schedule;

/**
 * Updates an existing recurring schedule rule.
 *
 * Only the recurrence-related fields (weekday, start_time, duration_minutes,
 * active) and the parent class assignment are mutable. Tenant ownership is
 * enforced upstream via SchedulePolicy and the BelongsToSchool global scope.
 *
 * @see CreateScheduleAction              For creating a new schedule rule
 * @see GenerateScheduledLessonsAction    For materializing concrete slots after changes
 */
class UpdateScheduleAction
{
    /**
     * @param  Schedule  $schedule  The schedule rule to update
     * @param  array  $data  Validated subset of: class_id, weekday, start_time, duration_minutes, active
     * @return Schedule The refreshed schedule
     */
    public function execute(Schedule $schedule, array $data): Schedule
    {
        // class_id is outside Schedule::$fillable so a plain fill() would drop
        // it silently. We assign each updatable field by name -- explicit list
        // here, validated subset upstream -- so the action remains the only
        // path that may move a schedule between classes.
        if (array_key_exists('class_id', $data)) {
            $schedule->class_id = $data['class_id'];
        }
        if (array_key_exists('weekday', $data)) {
            $schedule->weekday = $data['weekday'];
        }
        if (array_key_exists('start_time', $data)) {
            $schedule->start_time = $data['start_time'];
        }
        if (array_key_exists('duration_minutes', $data)) {
            $schedule->duration_minutes = $data['duration_minutes'];
        }
        if (array_key_exists('active', $data)) {
            $schedule->active = $data['active'];
        }

        $schedule->save();

        return $schedule->fresh();
    }
}
