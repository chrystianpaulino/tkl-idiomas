<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates and authorizes material upload requests.
 *
 * Authorization: admins and professors. File size limit is 50 MB (51200 KB).
 * Accepted formats: PDF, Word, PowerPoint, video, audio, and ZIP archives.
 */
class StoreMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin() || $this->user()->isProfessor();
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,ppt,pptx,mp4,mp3,zip', 'max:51200'],
        ];
    }
}
