<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
/**
 * Validates requests to create a new school (tenant).
 *
 * Admin-only. Slug must be URL-safe (lowercase alphanumeric + hyphens) and unique.
 */
class StoreSchoolRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()->isAdmin(); }
    public function rules(): array
    {
        return [
            'name'   => ['required', 'string', 'max:255'],
            'slug'   => ['required', 'string', 'max:63', 'unique:schools', 'regex:/^[a-z0-9\-]+$/'],
            'email'  => ['nullable', 'email', 'max:255'],
            'active' => ['boolean'],
        ];
    }
    public function messages(): array
    {
        return ['slug.regex' => 'O slug deve conter apenas letras minúsculas, números e hífens.'];
    }
}
