<?php

namespace App\Actions\Lessons;

use App\Models\Lesson;
use Illuminate\Support\Facades\DB;

class DeleteLessonAction
{
    // TODO(review): Business rule for absent_excused credit consumption is undefined.
    // Currently treated as credit-consuming (same as absent_unexcused).
    // When policy is decided, implement UpdateLessonStatusAction with lockForUpdate
    // to atomically transition status + adjust used_lessons. - business-logic-reviewer, 2026-03-13, Severity: High

    // Statuses that consumed a lesson credit from the package
    private const CREDIT_CONSUMING_STATUSES = ['completed', 'absent_unexcused', 'absent_excused'];

    public function execute(Lesson $lesson): void
    {
        DB::transaction(function () use ($lesson) {
            // Only decrement if this lesson consumed a credit
            if (in_array($lesson->status, self::CREDIT_CONSUMING_STATUSES)) {
                $lesson->package()->lockForUpdate()->decrement('used_lessons');
            }

            $lesson->delete();
        });
    }
}
