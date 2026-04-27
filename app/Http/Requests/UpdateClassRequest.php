<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates and authorizes requests to update an existing class.
 *
 * Authorization: admins and professors only. Mirrors StoreClassRequest rules,
 * including the cross-tenant defence on professor_id: a non-super_admin user
 * may only assign a professor that already exists, has the 'professor' role,
 * and belongs to the actor's school. The User model is not BelongsToSchool
 * scoped, so this filter is required to block IDOR on PUT /classes/{class}.
 */
class UpdateClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() || $this->user()->isProfessor();
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'name' => ['required', 'string', 'max:255'],
            'professor_id' => [
                'required',
                Rule::exists('users', 'id')
                    ->where('role', 'professor')
                    ->when(
                        ! $user->isSuperAdmin(),
                        fn ($rule) => $rule->where('school_id', $user->school_id),
                    ),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'professor_id.exists' => 'O professor selecionado é inválido ou não pertence à sua escola.',
        ];
    }
}
