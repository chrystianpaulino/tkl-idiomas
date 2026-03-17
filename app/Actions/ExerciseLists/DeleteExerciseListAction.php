<?php

namespace App\Actions\ExerciseLists;

use App\Models\ExerciseAnswer;
use App\Models\ExerciseList;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes an exercise list and cleans up all associated uploaded answer files.
 *
 * Must delete physical files from storage BEFORE the cascade delete removes the
 * database records, otherwise we lose the file_path references. After cleanup,
 * the list deletion cascades to exercises, submissions, and answers.
 */
class DeleteExerciseListAction
{
    /**
     * @param ExerciseList $list The list to delete (all submissions, answers, and files will be removed)
     */
    public function execute(ExerciseList $list): void
    {
        // Delete uploaded answer files before cascade delete removes records
        $filePaths = ExerciseAnswer::whereHas('submission', function ($query) use ($list) {
            $query->where('exercise_list_id', $list->id);
        })->whereNotNull('file_path')->pluck('file_path');

        foreach ($filePaths as $filePath) {
            Storage::disk('public')->delete($filePath);
        }

        $list->delete();
    }
}
