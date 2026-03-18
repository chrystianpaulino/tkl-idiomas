<?php

namespace App\Actions\ExerciseLists;

use App\Models\ExerciseAnswer;
use App\Models\ExerciseList;
use App\Models\ExerciseSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Handles student submission of answers to an exercise list.
 *
 * Supports both initial submission and re-submission (updating existing answers).
 * Uses firstOrCreate for the submission record and updateOrCreate for each answer,
 * making the operation idempotent. File answers replace the previous file on re-submit.
 *
 * Security: Only accepts exercise IDs that belong to the target list, preventing
 * cross-list answer injection via manipulated form data.
 *
 * The submitted_at timestamp is set only on the FIRST submission and never overwritten
 * on re-submissions, preserving the original submission time for grading fairness.
 *
 * @see ExerciseSubmission::isSubmitted() For checking if this is a re-submission
 */
class SubmitExerciseListAction
{
    /**
     * @param  ExerciseList  $list  The exercise list being answered
     * @param  User  $student  The student submitting answers
     * @param  array  $data  Validated data: answers[] keyed by exercise_id, each with answer_text and/or file
     * @return ExerciseSubmission The submission with answers eager-loaded
     *
     * @throws \RuntimeException If a file upload fails
     */
    public function execute(ExerciseList $list, User $student, array $data): ExerciseSubmission
    {
        return DB::transaction(function () use ($list, $student, $data) {
            $submission = ExerciseSubmission::firstOrCreate(
                [
                    'exercise_list_id' => $list->id,
                    'student_id' => $student->id,
                ],
                [
                    'completed' => false,
                ]
            );

            // Only accept exercise IDs that belong to this list (prevents cross-list answer injection)
            $validExerciseIds = $list->exercises()->pluck('id')->flip();

            foreach ($data['answers'] as $exerciseId => $answerData) {
                if (! $validExerciseIds->has($exerciseId)) {
                    continue;
                }

                $answer = ExerciseAnswer::updateOrCreate(
                    [
                        'exercise_submission_id' => $submission->id,
                        'exercise_id' => $exerciseId,
                    ],
                    [
                        'answer_text' => $answerData['answer_text'] ?? null,
                    ]
                );

                if (isset($answerData['file']) && $answerData['file'] !== null) {
                    // Delete old file if replacing
                    if ($answer->file_path) {
                        Storage::disk('public')->delete($answer->file_path);
                    }

                    $path = Storage::disk('public')->put("exercise-answers/{$answer->id}", $answerData['file']);

                    if ($path === false) {
                        throw new \RuntimeException('Falha ao armazenar o arquivo enviado.');
                    }

                    $answer->update(['file_path' => $path]);
                }
            }

            $updateData = ['completed' => true];

            // Only set submitted_at on the first submission; preserve the original timestamp on re-submits
            if (! $submission->isSubmitted()) {
                $updateData['submitted_at'] = now();
            }

            $submission->update($updateData);

            return $submission->load('answers');
        });
    }
}
