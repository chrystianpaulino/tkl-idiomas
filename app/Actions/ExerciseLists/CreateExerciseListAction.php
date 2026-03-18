<?php

namespace App\Actions\ExerciseLists;

use App\Models\ExerciseList;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates an exercise list with its questions in a single transaction.
 *
 * The list is assigned to a class and optionally linked to a specific lesson.
 * Exercises are created with auto-incremented order based on their array index.
 * Returns the list with exercises eager-loaded for immediate use by the frontend.
 *
 * @see DeleteExerciseListAction  For cleanup (including uploaded answer files)
 * @see SubmitExerciseListAction  For student submission handling
 */
class CreateExerciseListAction
{
    /**
     * @param  TurmaClass  $class  The class to assign this exercise list to
     * @param  User  $creator  The professor or admin creating the list
     * @param  array  $data  Validated data: title, description, due_date, lesson_id, exercises[]
     * @return ExerciseList The created list with exercises eager-loaded
     */
    public function execute(TurmaClass $class, User $creator, array $data): ExerciseList
    {
        return DB::transaction(function () use ($class, $creator, $data) {
            $exerciseList = ExerciseList::create([
                'class_id' => $class->id,
                'created_by' => $creator->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'lesson_id' => $data['lesson_id'] ?? null,
            ]);

            foreach ($data['exercises'] as $index => $exerciseData) {
                $exerciseList->exercises()->create([
                    'order' => $index + 1,
                    'question' => $exerciseData['question'],
                    'type' => $exerciseData['type'] ?? 'text',
                ]);
            }

            return $exerciseList->load('exercises');
        });
    }
}
