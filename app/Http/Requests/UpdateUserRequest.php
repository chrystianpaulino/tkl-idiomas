<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates and authorizes user update requests.
 *
 * Authorization is delegated to UserPolicy::update() against the user being
 * edited (route parameter). Email uniqueness check ignores the current user.
 * Password is not updatable through this request (only name, email, role).
 *
 * @see UserPolicy
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            && ($this->user()?->can('update', $target) ?? false);
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
     * Filtered through UserPolicy::assignRole(): super_admin (Gate::before
     * bypass) gets every role; school_admin gets only professor / aluno.
     */
    private function getAllowedRoles(): array
    {
        $actingUser = $this->user();

        if ($actingUser === null) {
            return [];
        }

        return array_values(array_filter(
            ['super_admin', 'school_admin', 'professor', 'aluno'],
            fn (string $role) => $actingUser->can('assignRole', [User::class, $role])
        ));
    }
}
