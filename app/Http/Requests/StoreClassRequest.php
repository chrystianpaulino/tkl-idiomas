<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates and authorizes requests to create a new class.
 *
 * Authorization: admins and professors only (controller further restricts to
 * admins via ClassPolicy::create). The professor_id must reference an existing
 * user with role 'professor'. For non-super_admin users, the professor MUST
 * belong to the same school as the authenticated admin (defense-in-depth
 * against cross-tenant IDOR — the User model is not BelongsToSchool scoped).
 */
class StoreClassRequest extends FormRequest
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
