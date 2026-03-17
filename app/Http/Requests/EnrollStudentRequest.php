<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates and authorizes student enrollment requests.
 *
 * Admin-only. Validates that student_id references an existing user.
 * Does not verify the user has role 'aluno' -- the EnrollStudentAction
 * simply adds the pivot record regardless of role.
 */
class EnrollStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'exists:users,id'],
        ];
    }
}
