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
            // max:200 caps the number of answers a single submission can carry.
            // Without it, a malicious aluno could POST a payload with millions
            // of entries and force the server into expensive validation +
            // file-system work (DoS).
            'answers' => ['required', 'array', 'max:200'],
            'answers.*.answer_text' => ['nullable', 'string', 'max:5000'],
            'answers.*.file' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,png', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'answers.max' => 'Uma submissão não pode conter mais de 200 respostas.',
        ];
    }
}
