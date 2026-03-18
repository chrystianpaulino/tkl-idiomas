<?php

namespace App\Actions\Lessons;

use App\Models\Lesson;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a lesson with configurable credit refund behavior.
 *
 * Supports two cancellation modes:
 * - Refund (default): Returns the consumed credit to the student's package using
 *   lockForUpdate for safe concurrent access, then sets status to 'cancelled'.
 * - No refund: Marks the lesson as 'absent_unexcused' (credit remains consumed).
 *
 * Only lessons in certain source states can be cancelled. Attempting to cancel
 * an already-cancelled lesson throws a LogicException.
 *
 * @see DeleteLessonAction For permanent removal (also refunds credits)
 */
class CancelLessonAction
{
    // Statuses that consumed a lesson credit -- refund is valid for these
    private const CREDIT_CONSUMING_STATUSES = ['completed', 'absent_unexcused', 'absent_excused'];

    /**
     * Cancel a lesson, optionally refunding the credit to the student's package.
     *
     * @param  Lesson  $lesson  The lesson to cancel (must be in a valid source status)
     * @param  bool  $refundLesson  True = credit returned to package; False = marked as unexcused absence
     * @return Lesson The refreshed lesson after status change
     *
     * @throws \LogicException If the lesson is in a status that cannot be cancelled
     */
    public function execute(Lesson $lesson, bool $refundLesson = true): Lesson
    {
        // Guard: only valid source states can be cancelled
        $validSourceStatuses = ['completed', 'scheduled', 'absent_excused', 'absent_unexcused'];
        if (! in_array($lesson->status, $validSourceStatuses)) {
            throw new \LogicException(
                "Cannot cancel a lesson with status '{$lesson->status}'."
            );
        }

        if ($refundLesson) {
            DB::transaction(function () use ($lesson) {
                if (in_array($lesson->status, self::CREDIT_CONSUMING_STATUSES)) {
                    if ($lesson->package_id !== null) {
                        $lesson->package()->lockForUpdate()->decrement('used_lessons');
                    }
                }
                $lesson->update(['status' => 'cancelled']);
            });
        } else {
            // No refund — mark as unexcused absence; credit already consumed or being consumed now
            $lesson->update(['status' => 'absent_unexcused']);
        }

        return $lesson->fresh();
    }
}
