<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates and authorizes requests to update an existing class.
 *
 * Authorization: admins and professors only. Same rules as StoreClassRequest.
 */
class UpdateClassRequest extends FormRequest
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
