<?php

namespace App\Http\Controllers;

use App\Actions\Lessons\DeleteLessonAction;
use App\Actions\Lessons\RegisterLessonAction;
use App\Http\Requests\StoreLessonRequest;
use App\Models\Lesson;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manages lesson registration and deletion within a class context.
 *
 * Lesson creation delegates to RegisterLessonAction (atomically consumes a package
 * credit). The store method catches RuntimeException from the action when no active
 * package is available and returns a user-friendly validation error.
 *
 * @see RegisterLessonAction For the credit consumption logic
 * @see DeleteLessonAction   For credit refund on lesson deletion
 * @see LessonPolicy         For authorization rules
 */
class LessonController extends Controller
{
    public function index(TurmaClass $class): Response
    {
        $this->authorize('view', $class);

        $lessons = $class->lessons()
            ->with(['student', 'professor'])
            ->latest('conducted_at')
            ->paginate(20);

        return Inertia::render('Lessons/Index', [
            'turmaClass' => $class->only('id', 'name'),
            'lessons' => $lessons,
        ]);
    }

    /**
     * Show the lesson registration form with enrolled students and their active package info.
     * Students without an active package will show null for active_package.
     */
    public function create(Request $request, TurmaClass $class): Response
    {
        $this->authorize('create', [Lesson::class, $class]);

        $students = $class->students->map(function (User $student) {
            $activePackage = $student->lessonPackages()->active()->orderBy('expires_at')->first();

            return [
                'id' => $student->id,
                'name' => $student->name,
                'active_package' => $activePackage ? [
                    'id' => $activePackage->id,
                    'remaining' => $activePackage->remaining,
                ] : null,
            ];
        });

        return Inertia::render('Lessons/Create', [
            'turmaClass' => $class->only('id', 'name'),
            'students' => $students,
        ]);
    }

    public function store(StoreLessonRequest $request, TurmaClass $class, RegisterLessonAction $action): RedirectResponse
    {
        $student = User::findOrFail($request->validated('student_id'));

        // C1: Guard against cross-tenant IDOR — professor must be in same school as student
        if (! auth()->user()->isSuperAdmin() && $student->school_id !== auth()->user()->school_id) {
            abort(403);
        }

        // C1: Verify the student is actually enrolled in this class
        if (! $class->students()->where('student_id', $student->id)->exists()) {
            abort(403, 'Student is not enrolled in this class.');
        }

        try {
            $action->execute($class, $student, $request->user(), $request->validated());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['student_id' => $e->getMessage()]);
        }

        return redirect()->route('classes.lessons.index', $class)->with('success', 'Aula registrada com sucesso.');
    }

    public function destroy(TurmaClass $class, Lesson $lesson, DeleteLessonAction $action): RedirectResponse
    {
        $this->authorize('delete', $lesson);

        $action->execute($lesson);

        return back()->with('success', 'Aula removida com sucesso.');
    }
}
