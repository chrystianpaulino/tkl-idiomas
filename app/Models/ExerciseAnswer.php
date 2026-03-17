<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ExerciseAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'exercise_submission_id',
        'exercise_id',
        'answer_text',
        'file_path',
    ];

    protected $appends = ['file_url'];

    public function getFileUrlAttribute(): ?string
    {
        if ($this->file_path) {
            return Storage::disk('public')->url($this->file_path);
        }

        return null;
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ExerciseSubmission::class, 'exercise_submission_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
