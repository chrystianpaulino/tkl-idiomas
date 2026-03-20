<?php

namespace App\Actions\Schedules;

use App\Models\Schedule;
use App\Models\ScheduledLesson;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Materializes concrete ScheduledLesson slots from recurring Schedule rules.
 *
 * Designed to be run periodically (e.g., via a scheduled artisan command) to ensure
 * there are always N weeks of future lesson slots available for each active schedule.
 * Uses firstOrCreate for idempotency -- safe to run multiple times without duplicating slots.
 *
 * @see CreateScheduleAction          For creating the recurring rule
 * @see ConfirmScheduledLessonAction  For converting a slot into actual Lesson records
 */
class GenerateScheduledLessonsAction
{
    /**
     * Generate scheduled lesson slots for a single schedule, looking N weeks ahead.
     *
     * Skips inactive schedules. Uses firstOrCreate keyed on (schedule_id, class_id,
     * scheduled_at) to ensure idempotency across repeated runs.
     *
     * @param  Schedule  $schedule  The recurring schedule rule to generate slots for
     * @param  int  $weeksAhead  How many weeks into the future to generate (default: 4)
     * @return Collection<int, ScheduledLesson> Only the newly created slots (not pre-existing ones)
     */
    public function execute(Schedule $schedule, int $weeksAhead = 4): Collection
    {
        if (! $schedule->active) {
            return collect();
        }

        $created = collect();
        $now = Carbon::now();
        $until = $now->copy()->addWeeks($weeksAhead);

        // Find the next occurrence of the weekday from today
        $next = $now->copy()->next($schedule->weekday);

        // Parse start_time (HH:MM:SS or HH:MM format from DB)
        [$hour, $minute] = explode(':', $schedule->start_time);

        while ($next->lte($until)) {
            $scheduledAt = $next->copy()->setTime((int) $hour, (int) $minute, 0);

            // Only create future slots
            if ($scheduledAt->gt($now)) {
                [$scheduledLesson, $wasCreated] = ScheduledLesson::firstOrCreate(
                    [
                        'schedule_id' => $schedule->id,
                        'class_id' => $schedule->class_id,
                        'scheduled_at' => $scheduledAt->toDateTimeString(),
                    ],
                    [
                        'status' => 'scheduled',
                        'school_id' => $schedule->school_id,
                    ]
                );

                if ($wasCreated) {
                    $created->push($scheduledLesson);
                }
            }

            $next->addWeek();
        }

        return $created;
    }

    /**
     * Generate slots for ALL active schedules across the platform.
     * Intended to be called by a scheduled artisan command (e.g., daily cron).
     *
     * @param  int  $weeksAhead  How many weeks into the future to generate
     * @return int Total number of newly created slots across all schedules
     */
    public function executeForAll(int $weeksAhead = 4): int
    {
        $total = 0;
        Schedule::where('active', true)->with('turmaClass')->each(function (Schedule $schedule) use ($weeksAhead, &$total) {
            $total += $this->execute($schedule, $weeksAhead)->count();
        });

        return $total;
    }
}
