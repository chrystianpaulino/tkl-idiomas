<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates and authorizes student enrollment requests.
 *
 * Admin-only. Validates that student_id references an existing user
 * with role 'aluno'. For non-super_admin users, the student MUST belong
 * to the same school as the authenticated admin (defense-in-depth against
 * cross-tenant IDOR — the User model is not BelongsToSchool scoped).
 */
class EnrollStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
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
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.exists' => 'O aluno selecionado é inválido ou não pertence à sua escola.',
        ];
    }
}
