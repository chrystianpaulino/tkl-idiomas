<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a request to confirm a ScheduledLesson slot.
 *
 * Confirmation triggers ConfirmScheduledLessonAction, which creates a Lesson
 * record per enrolled student and atomically debits each student's active
 * package. Notes is an optional human-readable annotation copied to all
 * Lesson records.
 */
class ConfirmScheduledLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('confirm', $this->route('scheduledLesson')) ?? false;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
