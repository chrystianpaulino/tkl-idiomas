<?php

namespace App\Http\Requests;

use App\Models\School;
use App\Policies\SchoolPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validates requests to create a new school (tenant).
 *
 * Authorization is delegated to SchoolPolicy::create() -- which returns false
 * for everyone, so only super_admin (granted by the global Gate::before
 * bypass) reaches the handler. Slug must be URL-safe and unique.
 *
 * @see SchoolPolicy
 */
class StoreSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', School::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:63', 'unique:schools', 'regex:/^[a-z0-9\-]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            // White-label identity (all optional; defaults applied at the DB layer).
            // SVG is intentionally excluded: SVG can carry inline <script>, which would
            // execute under the app's origin and become a stored XSS vector. Bitmap
            // formats only.
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'O slug deve conter apenas letras minúsculas, números e hífens.',
            'admin_email.unique' => 'Já existe um usuário com este e-mail.',
            'primary_color.regex' => 'A cor primária deve estar no formato #RRGGBB.',
            'secondary_color.regex' => 'A cor secundária deve estar no formato #RRGGBB.',
            'logo.image' => 'O logo deve ser uma imagem.',
            'logo.mimes' => 'O logo deve ser PNG, JPG, JPEG ou WebP.',
            'logo.max' => 'O logo não pode exceder 2 MB.',
        ];
    }
}
