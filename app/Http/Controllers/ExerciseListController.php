<?php

namespace App\Http\Controllers;

use App\Actions\ExerciseLists\CreateExerciseListAction;
use App\Actions\ExerciseLists\DeleteExerciseListAction;
use App\Http\Requests\StoreExerciseListRequest;
use App\Models\ExerciseList;
use App\Models\TurmaClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExerciseListController extends Controller
{
    public function index(Request $request, TurmaClass $class): Response
    {
        $this->authorize('viewAny', [ExerciseList::class, $class]);

        $userId = $request->user()->id;
        $isStudent = $request->user()->isAluno();

        $query = $class->exerciseLists()
            ->with('creator')
            ->withCount('exercises')
            ->withCount('submissions');

        // Eager-load the current student's own submission to avoid N+1
        if ($isStudent) {
            $query->with(['submissions' => fn ($q) => $q->where('student_id', $userId)]);
        }

        $exerciseLists = $query->latest()->get()->map(function ($list) use ($isStudent, $userId) {
            $data = $list->toArray();
            $data['is_overdue'] = $list->isOverdue();

            if ($isStudent) {
                $submission = $list->submissions->first();
                $data['my_submission'] = $submission ? [
                    'id' => $submission->id,
                    'completed' => $submission->completed,
                    'submitted_at' => $submission->submitted_at?->toISOString(),
                ] : null;
            }

            return $data;
        });

        return Inertia::render('ExerciseLists/Index', [
            'turmaClass' => $class->only('id', 'name'),
            'exerciseLists' => $exerciseLists,
            'can' => [
                'create' => $request->user()->can('create', [ExerciseList::class, $class]),
            ],
        ]);
    }

    public function create(Request $request, TurmaClass $class): Response
    {
        $this->authorize('create', [ExerciseList::class, $class]);

        $lessons = $class->lessons()->select('id', 'title')->latest()->get();

        return Inertia::render('ExerciseLists/Create', [
            'turmaClass' => $class->only('id', 'name'),
            'lessons' => $lessons,
        ]);
    }

    public function store(StoreExerciseListRequest $request, TurmaClass $class, CreateExerciseListAction $action): RedirectResponse
    {
        $this->authorize('create', [ExerciseList::class, $class]);

        $action->execute($class, $request->user(), $request->validated());

        return redirect()
            ->route('classes.exercise-lists.index', $class)
            ->with('success', 'Lista de exercicios criada com sucesso.');
    }

    public function show(Request $request, TurmaClass $class, ExerciseList $exerciseList): Response
    {
        $this->authorize('view', [$exerciseList, $class]);

        $exerciseList->load('exercises', 'creator', 'lesson');

        $data = [
            'turmaClass' => $class->only('id', 'name'),
            'exerciseList' => array_merge($exerciseList->toArray(), [
                'is_overdue' => $exerciseList->isOverdue(),
            ]),
        ];

        if ($request->user()->isAluno()) {
            $submission = $exerciseList->submissions()
                ->where('student_id', $request->user()->id)
                ->with('answers')
                ->first();

            $data['mySubmission'] = $submission;
        } else {
            $submissions = $exerciseList->submissions()
                ->with(['student', 'answers.exercise'])
                ->latest('submitted_at')
                ->get();

            $data['submissions'] = $submissions;
        }

        $data['can'] = [
            'delete' => $request->user()->can('delete', $exerciseList),
            'submit' => $request->user()->isAluno(),
        ];

        return Inertia::render('ExerciseLists/Show', $data);
    }

    public function destroy(TurmaClass $class, ExerciseList $exerciseList, DeleteExerciseListAction $action): RedirectResponse
    {
        $this->authorize('delete', $exerciseList);

        $action->execute($exerciseList);

        return redirect()
            ->route('classes.exercise-lists.index', $class)
            ->with('success', 'Lista de exercicios removida com sucesso.');
    }
}
