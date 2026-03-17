<?php

namespace App\Actions\Lessons;

use App\Models\Lesson;
use Illuminate\Support\Facades\DB;

class CancelLessonAction
{
    // Statuses that consumed a lesson credit — refund is valid for these
    private const CREDIT_CONSUMING_STATUSES = ['completed', 'absent_unexcused', 'absent_excused'];

    /**
     * Cancel a lesson.
     *
     * @param  Lesson  $lesson       The lesson to cancel
     * @param  bool    $refundLesson Whether to refund the lesson credit back to the package.
     *                               True (default) = credit is returned to student's package.
     *                               False = lesson counts as used (student no-show, unexcused).
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
