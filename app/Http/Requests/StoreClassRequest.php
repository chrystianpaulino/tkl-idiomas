<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates and authorizes requests to create a new class.
 *
 * Authorization: admins and professors only. The professor_id must reference
 * an existing user (though it does not verify the user actually has the professor role).
 */
class StoreClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() || $this->user()->isProfessor();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'professor_id' => ['required', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
