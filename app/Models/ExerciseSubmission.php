<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student's submission for an exercise list (one per student per list).
 *
 * Uniqueness is enforced by a composite unique constraint on (exercise_list_id, student_id).
 * The submitted_at timestamp is set only on the FIRST submission and never overwritten
 * on subsequent re-submissions, preserving the original submission time for grading fairness.
 *
 * @property int $id
 * @property int $exercise_list_id
 * @property int $student_id
 * @property \Illuminate\Support\Carbon|null $submitted_at  Set once on first submission; preserved on re-submits
 * @property bool $completed                                Whether all answers have been provided
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read ExerciseList $exerciseList
 * @property-read User $student
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ExerciseAnswer> $answers
 */
class ExerciseSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'exercise_list_id',
        'student_id',
        'submitted_at',
        'completed',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'completed' => 'boolean',
        ];
    }

    /**
     * The exercise list this submission is for.
     */
    public function exerciseList(): BelongsTo
    {
        return $this->belongsTo(ExerciseList::class);
    }

    /**
     * The student who made this submission.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Individual answers within this submission (one per exercise in the list).
     */
    public function answers(): HasMany
    {
        return $this->hasMany(ExerciseAnswer::class);
    }

    /**
     * Whether this submission has been formally submitted (submitted_at is set).
     * A submission may exist in draft state (created via firstOrCreate) before
     * the student completes all answers.
     */
    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }
}
