<?php

namespace App\Actions\Schedules;

use App\Actions\Lessons\RegisterLessonAction;
use App\Models\Lesson;
use App\Models\ScheduledLesson;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Confirms a scheduled lesson by creating actual Lesson records for all enrolled students.
 *
 * This is the bridge between the scheduling system and the billing system: for each
 * student enrolled in the class, it delegates to RegisterLessonAction (which atomically
 * consumes one credit from their active package). All student lessons are created within
 * a single outer transaction to ensure atomicity.
 *
 * After confirmation, the ScheduledLesson status changes to 'confirmed' and its lesson_id
 * points to the first Lesson created (representative record for group classes).
 *
 * @see RegisterLessonAction       Handles per-student credit consumption
 * @see CancelScheduledLessonAction For cancelling instead of confirming
 */
class ConfirmScheduledLessonAction
{
    public function __construct(
        private readonly RegisterLessonAction $registerLessonAction
    ) {}

    /**
     * Confirm a scheduled lesson for all enrolled students in the class.
     *
     * @param  ScheduledLesson  $scheduledLesson  Must be in 'scheduled' status
     * @param  User  $professor  The professor confirming/conducting the lesson
     * @param  array  $data  Optional: title, notes, conducted_at
     * @return Collection<int, Lesson> One Lesson per enrolled student
     *
     * @throws \LogicException If the scheduled lesson is not in 'scheduled' status
     * @throws \RuntimeException If no students are enrolled in the class
     * @throws ModelNotFoundException Propagated from RegisterLessonAction when an enrolled student has no active lesson package at confirmation time
     */
    public function execute(ScheduledLesson $scheduledLesson, User $professor, array $data = []): Collection
    {
        if (! $scheduledLesson->isScheduled()) {
            throw new \LogicException(
                "Cannot confirm a scheduled lesson with status '{$scheduledLesson->status}'."
            );
        }

        $turmaClass = $scheduledLesson->turmaClass;
        $students = $turmaClass->students; // enrolled students

        if ($students->isEmpty()) {
            throw new \RuntimeException('No students enrolled in this class.');
        }

        return DB::transaction(function () use ($scheduledLesson, $professor, $turmaClass, $students, $data) {
            $lessons = collect();

            foreach ($students as $student) {
                $lesson = $this->registerLessonAction->execute(
                    $turmaClass,
                    $student,
                    $professor,
                    array_merge([
                        'title' => $data['title'] ?? 'Aula confirmada',
                        'notes' => $data['notes'] ?? null,
                        'conducted_at' => $data['conducted_at'] ?? $scheduledLesson->scheduled_at->toDateTimeString(),
                    ], [])
                );

                $lessons->push($lesson);
            }

            // Update the scheduled_lesson status and link to the first lesson created
            // (for group classes, link to first lesson as the representative record).
            // lesson_id is outside $fillable so we set it via direct assignment;
            // status remains in $fillable but we use direct assignment for symmetry.
            $scheduledLesson->status = 'confirmed';
            $scheduledLesson->lesson_id = $lessons->first()?->id;
            $scheduledLesson->save();

            Audit::log('lesson.scheduled_confirmed', [
                'scheduled_lesson_id' => $scheduledLesson->id,
                'class_id' => $turmaClass->id,
                'school_id' => $scheduledLesson->school_id,
                'professor_id' => $professor->id,
                'lesson_count' => $lessons->count(),
                'student_ids' => $students->pluck('id')->all(),
                'first_lesson_id' => $lessons->first()?->id,
            ]);

            return $lessons;
        });
    }
}
