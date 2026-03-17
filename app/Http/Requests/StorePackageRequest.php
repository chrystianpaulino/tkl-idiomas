<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates and authorizes requests to create a new lesson package.
 *
 * Admin-only. Expiration date must be in the future if provided;
 * null expires_at means the package never expires.
 */
class StorePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'total_lessons' => ['required', 'integer', 'min:1', 'max:999'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ];
    }
}
