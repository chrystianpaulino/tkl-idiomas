<?php

namespace App\Actions\Schedules;

use App\Models\ScheduledLesson;
use App\Support\Audit;

/**
 * Cancels a scheduled lesson slot before it has been confirmed.
 *
 * This only applies to ScheduledLesson records (the calendar slot), NOT to actual
 * Lesson records. If a lesson has already been confirmed (status = 'confirmed'),
 * use CancelLessonAction instead to handle the credit refund properly.
 *
 * @see CancelLessonAction For cancelling already-confirmed lessons (with credit refund)
 */
class CancelScheduledLessonAction
{
    /**
     * @param  ScheduledLesson  $scheduledLesson  Must be in 'scheduled' status (not confirmed or already cancelled)
     * @param  string|null  $reason  Optional cancellation reason for record-keeping
     * @return ScheduledLesson The refreshed record with 'cancelled' status
     *
     * @throws \LogicException If the slot is already confirmed or already cancelled
     */
    public function execute(ScheduledLesson $scheduledLesson, ?string $reason = null): ScheduledLesson
    {
        if ($scheduledLesson->isConfirmed()) {
            throw new \LogicException('Cannot cancel an already confirmed lesson. Use CancelLessonAction to cancel the registered lesson.');
        }

        if ($scheduledLesson->isCancelled()) {
            throw new \LogicException('This scheduled lesson is already cancelled.');
        }

        $scheduledLesson->update([
            'status' => 'cancelled',
            'cancelled_reason' => $reason,
        ]);

        Audit::log('lesson.scheduled_cancelled', [
            'scheduled_lesson_id' => $scheduledLesson->id,
            'class_id' => $scheduledLesson->class_id,
            'school_id' => $scheduledLesson->school_id,
            'reason' => $reason,
        ]);

        return $scheduledLesson->fresh();
    }
}
