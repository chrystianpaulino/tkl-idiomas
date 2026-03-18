<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validates and authorizes user creation requests.
 *
 * Admin-only. Role is validated to be one of the three allowed values.
 * The validated role value is passed to CreateUserAction which assigns
 * it via direct attribute setting (bypassing $fillable).
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
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
