<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validates the password chosen by an invitee accepting their invite.
 *
 * Authorization is implicit: possession of a valid (non-expired, non-used)
 * token IS the authorization for setting that account's password. The
 * AcceptInviteController re-checks the token before applying the password,
 * so this request only needs to enforce the password strength policy --
 * the same Password::defaults() used by registration and password reset.
 */
class AcceptInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The invite token in the route IS the authorization. The controller
        // verifies the token before this request's validated data is applied.
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
