<?php

namespace App\Http\Controllers;

use App\Actions\Classes\EnrollStudentAction;
use App\Actions\Classes\UnenrollStudentAction;
use App\Http\Requests\EnrollStudentRequest;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Handles student enrollment and unenrollment in classes.
 *
 * Admin-only (enforced by EnrollStudentRequest::authorize). Enrollment is
 * idempotent (safe to call repeatedly). Unenrollment does not delete historical
 * data (lessons, submissions).
 */
class EnrollmentController extends Controller
{
    public function store(EnrollStudentRequest $request, TurmaClass $class, EnrollStudentAction $action): RedirectResponse
    {
        $student = User::findOrFail($request->validated('student_id'));
        $action->execute($class, $student);

        return back()->with('success', 'Aluno matriculado com sucesso.');
    }

    public function destroy(TurmaClass $class, User $student, UnenrollStudentAction $action): RedirectResponse
    {
        $action->execute($class, $student);

        return back()->with('success', 'Aluno removido da turma.');
    }
}
