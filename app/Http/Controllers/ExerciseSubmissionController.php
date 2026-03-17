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

/**
 * Handles student exercise submissions (answer form + submission processing).
 *
 * The create action shows the answer form, pre-filling with existing answers
 * if the student has a draft/previous submission. The store action delegates
 * to SubmitExerciseListAction which handles both first-time and re-submissions.
 *
 * @see SubmitExerciseListAction For the submission/re-submission logic
 * @see ExerciseListPolicy       For the 'submit' authorization check
 */
class ExerciseSubmissionController extends Controller
{
    /**
     * Show the answer form for an exercise list, pre-filled with any existing answers.
     */
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
