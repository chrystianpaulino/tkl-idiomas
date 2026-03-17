<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExerciseSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAluno();
    }

    public function rules(): array
    {
        return [
            'answers' => ['required', 'array'],
            'answers.*.answer_text' => ['nullable', 'string', 'max:5000'],
            'answers.*.file' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,png', 'max:10240'],
        ];
    }
}
