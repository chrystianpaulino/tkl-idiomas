<?php

namespace App\Actions\ExerciseLists;

use App\Models\ExerciseList;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateExerciseListAction
{
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
