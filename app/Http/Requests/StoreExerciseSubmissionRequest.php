<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates exercise submission requests from students.
 *
 * Student-only (aluno). Each answer may contain text, a file upload, or both.
 * File uploads are limited to 10 MB. Cross-list answer injection is prevented
 * at the action level (SubmitExerciseListAction), not here.
 */
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
