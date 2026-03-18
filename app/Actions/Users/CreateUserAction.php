<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Creates a new user with direct role assignment, bypassing mass-assignment protection.
 *
 * Because 'role' is intentionally excluded from User::$fillable to prevent privilege
 * escalation, this action assigns the role via direct attribute setting ($user->role = ...).
 * This is one of only two places where role is set (the other is UserController::update).
 *
 * Password is hashed using Hash::make even though User casts password as 'hashed',
 * because we are setting it before save (not via fill/create).
 */
class CreateUserAction
{
    /**
     * @param  array  $data  Validated data: name, email, password, role, school_id (optional)
     * @return User The persisted user with role assigned
     */
    public function execute(array $data): User
    {
        $user = new User;
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = Hash::make($data['password']);
        $user->role = $data['role'];
        if (isset($data['school_id'])) {
            $user->school_id = $data['school_id'];
        }
        $user->save();

        return $user;
    }
}
