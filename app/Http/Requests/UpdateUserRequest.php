<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates and authorizes user update requests.
 *
 * Admin-only. Email uniqueness check ignores the current user being edited.
 * Password is not updatable through this request (only name, email, role).
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($this->route('user'))],
            'role' => [
                'required',
                'string',
                Rule::in($this->getAllowedRoles()),
            ],
        ];
    }

    /**
     * Returns the roles the acting user is allowed to assign.
     *
     * super_admin can assign any role. school_admin and legacy admin
     * can only assign non-privileged roles (professor, aluno).
     */
    private function getAllowedRoles(): array
    {
        $actingUser = $this->user();

        if ($actingUser?->isSuperAdmin()) {
            return ['super_admin', 'school_admin', 'admin', 'professor', 'aluno'];
        }

        // school_admin and legacy admin can only assign non-privileged roles
        return ['professor', 'aluno'];
    }
}
