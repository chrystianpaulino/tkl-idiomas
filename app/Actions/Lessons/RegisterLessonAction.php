<?php

namespace App\Actions\Lessons;

use App\Models\Lesson;
use App\Models\LessonPackage;
use App\Models\TurmaClass;
use App\Models\User;
use App\Notifications\PackageAlmostFinished;
use App\Notifications\PackageFinished;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Atomically registers a new lesson for a student against their earliest-expiring active package.
 *
 * This is the most critical action in the billing system. It uses a database transaction
 * with a pessimistic lock (lockForUpdate) on the LessonPackage row to prevent TOCTOU
 * race conditions -- if two requests arrive simultaneously for the last remaining credit,
 * only one will succeed. The other will re-read after the lock and find no remaining credits.
 *
 * Also notifies the student when their package is exhausted or nearly exhausted.
 *
 * @see DeleteLessonAction  For the reverse operation (credit refund on deletion)
 * @see CancelLessonAction  For cancellation with optional credit refund
 */
class RegisterLessonAction
{
    /**
     * Register a lesson, consuming one credit from the student's active package.
     *
     * Selects the earliest-expiring active package (FIFO consumption). Re-verifies
     * the package is still active after acquiring the lock to guard against races.
     *
     * @param  TurmaClass  $turmaClass  The class in which the lesson is being conducted
     * @param  User  $student  Must have at least one active LessonPackage
     * @param  User  $professor  The professor conducting the lesson
     * @param  array  $data  Validated data: title (required), notes, conducted_at
     * @return Lesson The persisted lesson record
     *
     * @throws ModelNotFoundException If no active package exists
     * @throws \RuntimeException If the package has no remaining credits after the lock is acquired
     */
    public function execute(TurmaClass $turmaClass, User $student, User $professor, array $data): Lesson
    {
        $lesson = DB::transaction(function () use ($turmaClass, $student, $professor, $data) {
            // H8: Use COALESCE to sort NULL expires_at last — never-expiring packages
            // should be consumed after time-limited ones
            $package = LessonPackage::where('student_id', $student->id)
                ->active()
                ->orderByRaw("COALESCE(expires_at, '9999-12-31') ASC")
                ->lockForUpdate()
                ->firstOrFail();

            // Re-verify package is still active after lock (race condition guard)
            if (! $package->isActive()) {
                throw new \RuntimeException('No active lesson package available for this student.');
            }

            $package->increment('used_lessons');
            $package->refresh(); // reload to get updated used_lessons

            // Foreign keys (class_id, student_id, professor_id, package_id) and
            // school_id are intentionally outside Lesson::$fillable so they can
            // never be reassigned via mass-assignment. We set them explicitly
            // here -- this is one of the only writers that may.
            $lesson = new Lesson;
            $lesson->class_id = $turmaClass->id;
            $lesson->student_id = $student->id;
            $lesson->professor_id = $professor->id;
            $lesson->package_id = $package->id;
            $lesson->title = $data['title'];
            $lesson->notes = $data['notes'] ?? null;
            $lesson->conducted_at = $data['conducted_at'] ?? now();
            $lesson->save();

            return $lesson;
        });

        // M4: Dispatch notifications AFTER transaction commits to avoid false alarms on rollback
        // L10: Wrap in try/catch so notification failures don't break the lesson registration flow
        $package = $lesson->package;
        if ($package !== null) {
            DB::afterCommit(function () use ($student, $package) {
                try {
                    if ($package->isExhausted()) {
                        $student->notify(new PackageFinished($package));
                    } elseif ($package->remaining === 1) {
                        $student->notify(new PackageAlmostFinished($package));
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to send package notification', [
                        'student_id' => $student->id,
                        'package_id' => $package->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        }

        return $lesson;
    }
}
