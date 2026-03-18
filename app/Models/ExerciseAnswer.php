<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A student's answer to a single Exercise question within a submission.
 *
 * Answers can be text-based (answer_text) or file-based (file_path), depending
 * on the Exercise type. The file_url accessor is auto-appended to JSON so the
 * frontend always receives a ready-to-use URL for file answers.
 *
 * @property int $id
 * @property int $exercise_submission_id
 * @property int $exercise_id
 * @property string|null $answer_text Free-text answer (for 'text' type exercises)
 * @property string|null $file_path Relative path on the 'public' disk (for 'file' type exercises)
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read string|null $file_url      Full public URL for the uploaded file, or null if no file
 * @property-read ExerciseSubmission $submission
 * @property-read Exercise $exercise
 */
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

    /**
     * Build the full public URL for the uploaded answer file.
     * Returns null when no file has been uploaded for this answer.
     */
    public function getFileUrlAttribute(): ?string
    {
        if ($this->file_path) {
            return Storage::disk('public')->url($this->file_path);
        }

        return null;
    }

    /**
     * The submission this answer belongs to.
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(ExerciseSubmission::class, 'exercise_submission_id');
    }

    /**
     * The specific exercise question this answer responds to.
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
