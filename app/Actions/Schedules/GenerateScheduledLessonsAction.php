<?php

namespace App\Actions\Schedules;

use App\Models\Schedule;
use App\Models\ScheduledLesson;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GenerateScheduledLessonsAction
{
    /**
     * Generate scheduled lesson slots for a schedule, N weeks ahead.
     * Idempotent: uses firstOrCreate to skip existing slots.
     *
     * @param  Schedule  $schedule
     * @param  int       $weeksAhead  How many weeks of future slots to ensure exist
     * @return Collection  Newly created ScheduledLesson instances
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
                        'schedule_id'  => $schedule->id,
                        'class_id'     => $schedule->class_id,
                        'scheduled_at' => $scheduledAt->toDateTimeString(),
                    ],
                    [
                        'status' => 'scheduled',
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
     * Run for ALL active schedules. Called by the artisan command.
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
