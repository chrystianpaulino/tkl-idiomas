<?php

namespace App\Actions\Schedules;

use App\Actions\Lessons\RegisterLessonAction;
use App\Models\ScheduledLesson;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConfirmScheduledLessonAction
{
    public function __construct(
        private readonly RegisterLessonAction $registerLessonAction
    ) {}

    /**
     * Confirm a scheduled lesson for all enrolled students.
     * Calls RegisterLessonAction per student inside a single outer transaction.
     *
     * @param  ScheduledLesson  $scheduledLesson
     * @param  User             $professor
     * @param  array            $data  title, notes, conducted_at (optional)
     * @return Collection  Created Lesson instances
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
                        'title'        => $data['title'] ?? 'Aula confirmada',
                        'notes'        => $data['notes'] ?? null,
                        'conducted_at' => $data['conducted_at'] ?? $scheduledLesson->scheduled_at->toDateTimeString(),
                    ], [])
                );

                $lessons->push($lesson);
            }

            // Update the scheduled_lesson status and link to the first lesson created
            // (for group classes, link to first lesson as the representative record)
            $scheduledLesson->update([
                'status'    => 'confirmed',
                'lesson_id' => $lessons->first()?->id,
            ]);

            return $lessons;
        });
    }
}
