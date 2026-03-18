<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates requests to register a payment for a lesson package.
 *
 * Admin-only. Amount must be positive, paid_at cannot be in the future,
 * and currency (if provided) must be a supported ISO 4217 code.
 */
class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:pix,cash,card,transfer,other'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'currency' => ['sometimes', 'string', 'size:3', 'regex:/^[A-Z]{3}$/', Rule::in(['BRL', 'USD', 'EUR'])],
        ];
    }
}
