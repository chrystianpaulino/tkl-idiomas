<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'amount'  => ['required', 'numeric', 'min:0.01'],
            'method'  => ['required', 'in:pix,cash,card,transfer,other'],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'notes'   => ['nullable', 'string', 'max:1000'],
            'currency'=> ['sometimes', 'string', 'size:3'],
        ];
    }
}
