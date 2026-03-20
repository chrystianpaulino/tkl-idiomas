<?php

namespace App\Actions\Lessons;

use App\Models\Lesson;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a lesson and refunds the consumed credit back to the package when applicable.
 *
 * Uses a database transaction with lockForUpdate on the package row to safely
 * decrement used_lessons. Only refunds credits for statuses that originally consumed
 * one (completed, absent_unexcused, absent_excused). Cancelled lessons did not
 * consume a credit, so no decrement occurs.
 *
 * @see RegisterLessonAction  For the forward operation (credit consumption)
 */
class DeleteLessonAction
{
    // TODO(review): Business rule for absent_excused credit consumption is undefined.
    // Currently treated as credit-consuming (same as absent_unexcused).
    // When policy is decided, implement UpdateLessonStatusAction with lockForUpdate
    // to atomically transition status + adjust used_lessons. - business-logic-reviewer, 2026-03-13, Severity: High

    // Statuses that consumed a lesson credit from the package
    private const CREDIT_CONSUMING_STATUSES = ['completed', 'absent_unexcused', 'absent_excused'];

    /**
     * Delete the lesson and refund the credit to its package if applicable.
     *
     * @param  Lesson  $lesson  The lesson to delete; must have a valid package_id for credit refund
     */
    public function execute(Lesson $lesson): void
    {
        DB::transaction(function () use ($lesson) {
            // Only decrement if this lesson consumed a credit and has a package
            if ($lesson->package_id !== null && in_array($lesson->status, self::CREDIT_CONSUMING_STATUSES)) {
                $lesson->package()->lockForUpdate()->decrement('used_lessons');
            }

            $lesson->delete();
        });
    }
}
