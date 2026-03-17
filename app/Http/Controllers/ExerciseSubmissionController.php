<?php

namespace App\Http\Controllers;

use App\Actions\ExerciseLists\SubmitExerciseListAction;
use App\Http\Requests\StoreExerciseSubmissionRequest;
use App\Models\ExerciseList;
use App\Models\TurmaClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExerciseSubmissionController extends Controller
{
    public function create(Request $request, TurmaClass $class, ExerciseList $exerciseList): Response
    {
        $this->authorize('submit', [$exerciseList, $class]);

        $exerciseList->load('exercises');

        // Load existing submission with answers if any
        $submission = $exerciseList->submissions()
            ->where('student_id', $request->user()->id)
            ->with('answers')
            ->first();

        return Inertia::render('ExerciseLists/Submit', [
            'turmaClass' => $class->only('id', 'name'),
            'exerciseList' => array_merge($exerciseList->toArray(), [
                'is_overdue' => $exerciseList->isOverdue(),
            ]),
            'existingSubmission' => $submission,
        ]);
    }

    public function store(StoreExerciseSubmissionRequest $request, TurmaClass $class, ExerciseList $exerciseList, SubmitExerciseListAction $action): RedirectResponse
    {
        $this->authorize('submit', [$exerciseList, $class]);

        $action->execute($exerciseList, $request->user(), $request->validated());

        return redirect()
            ->route('classes.exercise-lists.show', [$class, $exerciseList])
            ->with('success', 'Respostas enviadas com sucesso.');
    }
}
