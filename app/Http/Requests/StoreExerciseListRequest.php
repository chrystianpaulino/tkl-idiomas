<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExerciseListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() || $this->user()->isProfessor();
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'lesson_id' => ['nullable', Rule::exists('lessons', 'id')->where('class_id', $this->route('class')->id)],
            'exercises' => ['required', 'array', 'min:1'],
            'exercises.*.question' => ['required', 'string', 'max:2000'],
            'exercises.*.type' => ['required', 'in:text,file'],
        ];
    }
}
