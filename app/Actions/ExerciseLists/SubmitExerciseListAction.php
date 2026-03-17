<?php

namespace App\Actions\ExerciseLists;

use App\Models\ExerciseAnswer;
use App\Models\ExerciseList;
use App\Models\ExerciseSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SubmitExerciseListAction
{
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
