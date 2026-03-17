<?php

namespace App\Http\Controllers;

use App\Actions\Classes\CreateClassAction;
use App\Http\Requests\StoreClassRequest;
use App\Http\Requests\UpdateClassRequest;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClassController extends Controller
{
    public function index(Request $request): Response
    {
        $classes = TurmaClass::with('professor')
            ->when($request->user()->isProfessor(), fn ($q) => $q->where('professor_id', $request->user()->id))
            ->when($request->user()->isAluno(), fn ($q) => $q->whereHas('students', fn ($sq) => $sq->where('users.id', $request->user()->id)))
            ->paginate(15);

        return Inertia::render('Classes/Index', [
            'classes' => $classes,
            'can' => ['create' => $request->user()->can('create', TurmaClass::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', TurmaClass::class);

        return Inertia::render('Classes/Create', [
            'professors' => User::where('role', 'professor')->get(['id', 'name']),
        ]);
    }

    public function store(StoreClassRequest $request, CreateClassAction $action): RedirectResponse
    {
        $this->authorize('create', TurmaClass::class);

        $professor = User::findOrFail($request->validated('professor_id'));
        $action->execute($request->validated(), $professor);

        return redirect()->route('classes.index')->with('success', 'Turma criada com sucesso.');
    }

    public function show(Request $request, TurmaClass $class): Response
    {
        $this->authorize('view', $class);

        $class->load([
            'professor',
            'students',
            'lessons' => fn ($q) => $q->with(['student', 'professor'])->latest('conducted_at')->limit(10),
            'materials.uploader',
        ]);

        $exerciseListsCount = $class->exerciseLists()->count();

        $availableStudents = $request->user()->isAdmin()
            ? User::where('role', 'aluno')->whereNotIn('id', $class->students->pluck('id'))->get(['id', 'name'])
            : collect();

        return Inertia::render('Classes/Show', [
            'turmaClass' => array_merge($class->toArray(), [
                'students' => $class->students->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'remaining_lessons' => $s->remaining_lessons,
                ]),
                'exercise_lists_count' => $exerciseListsCount,
            ]),
            'can' => [
                'enroll' => $request->user()->isAdmin(),
                'registerLesson' => $request->user()->can('create', [Lesson::class, $class]),
                'uploadMaterial' => $request->user()->can('create', [Material::class, $class]),
                'edit' => $request->user()->can('update', $class),
                'createExerciseList' => $request->user()->can('create', [\App\Models\ExerciseList::class, $class]),
            ],
            'availableStudents' => $availableStudents,
        ]);
    }

    public function edit(Request $request, TurmaClass $class): Response
    {
        $this->authorize('update', $class);

        return Inertia::render('Classes/Edit', [
            'turmaClass' => $class,
            'professors' => User::where('role', 'professor')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateClassRequest $request, TurmaClass $class): RedirectResponse
    {
        $this->authorize('update', $class);

        $class->update($request->validated());

        return redirect()->route('classes.show', $class)->with('success', 'Turma atualizada com sucesso.');
    }

    public function destroy(Request $request, TurmaClass $class): RedirectResponse
    {
        $this->authorize('delete', $class);

        $class->delete();

        return redirect()->route('classes.index')->with('success', 'Turma excluída com sucesso.');
    }
}
