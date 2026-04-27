<?php

namespace App\Http\Requests;

use App\Actions\Users\InviteUserAction;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates and authorizes user creation requests.
 *
 * Authorization is delegated to UserPolicy::create(). The role allow-list
 * is computed via UserPolicy::assignRole() so super_admin (granted by the
 * global Gate::before bypass) may assign any role while school_admin is
 * limited to professor/aluno -- preventing horizontal privilege escalation.
 *
 * Wave 9: password is no longer collected here. The user defines their own
 * password through the invite link (see InviteUserAction + AcceptInviteController).
 * `phone` is optional and forwarded to the new User row when present.
 *
 * @see UserPolicy
 * @see InviteUserAction
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'role' => [
                'required',
                'string',
                Rule::in($this->getAllowedRoles()),
            ],
            // Optional contact field. Loose validation -- phone formats vary
            // across regions and a strict regex would block legitimate values.
            'phone' => ['nullable', 'string', 'max:30'],
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
