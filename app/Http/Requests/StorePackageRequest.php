<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates and authorizes requests to create a new lesson package.
 *
 * Admin-only. Expiration date must be in the future if provided;
 * null expires_at means the package never expires. Price and currency
 * are required because every package sale is a billable transaction --
 * Payment.amount remains independent so admins can register partial
 * payments or discounts against the package price.
 *
 * Price uses decimal(8,2): max 999999.99 (covers any realistic package).
 * Currency mirrors StorePaymentRequest: BRL/USD/EUR ISO 4217 only.
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
            'price' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/', Rule::in(['BRL', 'USD', 'EUR'])],
        ];
    }
}
