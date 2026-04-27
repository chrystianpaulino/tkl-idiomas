<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates and authorizes requests to register a new lesson.
 *
 * Authorization is context-aware: admins can register lessons for any class,
 * while professors can only register for classes they teach (professor_id check
 * against the route-bound TurmaClass). Students cannot register lessons.
 *
 * The student_id is filtered by role and tenant: non-super_admin actors may
 * only target a student with role 'aluno' that belongs to the same school as
 * the actor. The User model is not BelongsToSchool scoped, so this defence
 * is required to block cross-tenant IDOR via the lesson registration route.
 */
class StoreLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $turmaClass = $this->route('class');

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isProfessor()) {
            return $turmaClass && $turmaClass->professor_id === $user->id;
        }

        return false;
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'student_id' => [
                'required',
                Rule::exists('users', 'id')
                    ->where('role', 'aluno')
                    ->when(
                        ! $user->isSuperAdmin(),
                        fn ($rule) => $rule->where('school_id', $user->school_id),
                    ),
            ],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'conducted_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.exists' => 'O aluno selecionado é inválido ou não pertence à sua escola.',
        ];
    }
}
