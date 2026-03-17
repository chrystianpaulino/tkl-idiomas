<?php

namespace App\Actions\Lessons;

use App\Models\Lesson;
use App\Models\LessonPackage;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
     * @param TurmaClass $turmaClass The class in which the lesson is being conducted
     * @param User $student          Must have at least one active LessonPackage
     * @param User $professor        The professor conducting the lesson
     * @param array $data            Validated data: title (required), notes, conducted_at
     * @return Lesson                The persisted lesson record
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If no active package exists
     * @throws \RuntimeException If the package has no remaining credits after the lock is acquired
     */
    public function execute(TurmaClass $turmaClass, User $student, User $professor, array $data): Lesson
    {
        return DB::transaction(function () use ($turmaClass, $student, $professor, $data) {
            // Lock the earliest-expiring active package for this student
            $package = LessonPackage::where('student_id', $student->id)
                ->active()
                ->orderBy('expires_at')
                ->lockForUpdate()
                ->firstOrFail();

            // Re-verify package is still active after lock (race condition guard)
            if (!$package->isActive()) {
                throw new \RuntimeException('No active lesson package available for this student.');
            }

            $package->increment('used_lessons');
            $package->refresh(); // reload to get updated used_lessons

            // Notifications fire INSIDE the transaction. If Lesson::create() fails after this point
            // and the transaction rolls back, these notifications will have already been dispatched --
            // the student may receive a false "package exhausted" alert. Accepted trade-off;
            // revisit if notification reliability becomes a requirement.
            if ($package->isExhausted()) {
                $student->notify(new \App\Notifications\PackageFinished($package));
            } elseif ($package->remaining === 1) {
                $student->notify(new \App\Notifications\PackageAlmostFinished($package));
            }

            return Lesson::create([
                'class_id' => $turmaClass->id,
                'student_id' => $student->id,
                'professor_id' => $professor->id,
                'package_id' => $package->id,
                'title' => $data['title'],
                'notes' => $data['notes'] ?? null,
                'conducted_at' => $data['conducted_at'] ?? now(),
            ]);
        });
    }
}
