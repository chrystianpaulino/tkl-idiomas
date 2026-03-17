<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $turmaClass = $this->route('class');

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isProfessor()) {
            return $turmaClass && $turmaClass->professor_id === $user->id;
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'conducted_at' => ['nullable', 'date'],
        ];
    }
}
