<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An individual question within an ExerciseList.
 *
 * Exercises are ordered by the 'order' column for consistent display.
 * Each exercise has a type ('text' or 'file') that determines how students
 * should answer -- either with free-form text or by uploading a file.
 *
 * @property int $id
 * @property int $exercise_list_id
 * @property int $order               Display position within the list (1-based)
 * @property string $question          The question text
 * @property string $type              Answer type: 'text' or 'file'
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read ExerciseList $exerciseList
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ExerciseAnswer> $answers
 */
class Exercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'exercise_list_id',
        'order',
        'question',
        'type',
    ];

    /**
     * The exercise list this question belongs to.
     */
    public function exerciseList(): BelongsTo
    {
        return $this->belongsTo(ExerciseList::class);
    }

    /**
     * Student answers to this specific question (one per submission).
     */
    public function answers(): HasMany
    {
        return $this->hasMany(ExerciseAnswer::class);
    }
}
