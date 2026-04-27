<?php

namespace App\Http\Requests;

use App\Models\School;
use App\Policies\SchoolPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates requests to update an existing school.
 *
 * Authorization is delegated to SchoolPolicy::update() against the school in
 * the route, which enforces same-school ownership for school_admin (super_admin
 * is implicitly allowed by Gate::before). Slug uniqueness ignores the current
 * school being edited.
 *
 * @see SchoolPolicy
 */
class UpdateSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('school');

        return $target instanceof School
            && ($this->user()?->can('update', $target) ?? false);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:63', Rule::unique('schools')->ignore($this->route('school')), 'regex:/^[a-z0-9\-]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'active' => ['boolean'],
            // White-label identity (all optional).
            // SVG is intentionally excluded: SVG can carry inline <script>, which would
            // execute under the app's origin and become a stored XSS vector. Bitmap
            // formats only.
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'O slug deve conter apenas letras minúsculas, números e hífens.',
            'primary_color.regex' => 'A cor primária deve estar no formato #RRGGBB.',
            'secondary_color.regex' => 'A cor secundária deve estar no formato #RRGGBB.',
            'logo.image' => 'O logo deve ser uma imagem.',
            'logo.mimes' => 'O logo deve ser PNG, JPG, JPEG ou WebP.',
            'logo.max' => 'O logo não pode exceder 2 MB.',
        ];
    }
}
