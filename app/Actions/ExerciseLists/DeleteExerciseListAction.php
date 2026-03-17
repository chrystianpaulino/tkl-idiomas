<?php

namespace App\Actions\ExerciseLists;

use App\Models\ExerciseAnswer;
use App\Models\ExerciseList;
use Illuminate\Support\Facades\Storage;

class DeleteExerciseListAction
{
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
