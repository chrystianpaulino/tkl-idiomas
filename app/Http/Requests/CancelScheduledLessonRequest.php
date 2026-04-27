<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a request to cancel a ScheduledLesson slot.
 *
 * Cancellation does NOT debit any package -- it only marks the slot as
 * cancelled and stores the (optional) reason for record-keeping.
 */
class CancelScheduledLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cancel', $this->route('scheduledLesson')) ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
