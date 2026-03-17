<?php

namespace App\Actions\Schedules;

use App\Models\ScheduledLesson;

class CancelScheduledLessonAction
{
    public function execute(ScheduledLesson $scheduledLesson, ?string $reason = null): ScheduledLesson
    {
        if ($scheduledLesson->isConfirmed()) {
            throw new \LogicException('Cannot cancel an already confirmed lesson. Use CancelLessonAction to cancel the registered lesson.');
        }

        if ($scheduledLesson->isCancelled()) {
            throw new \LogicException('This scheduled lesson is already cancelled.');
        }

        $scheduledLesson->update([
            'status'           => 'cancelled',
            'cancelled_reason' => $reason,
        ]);

        return $scheduledLesson->fresh();
    }
}
