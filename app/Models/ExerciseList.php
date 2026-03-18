<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A homework/exercise list assigned to all students in a class.
 *
 * An exercise list contains one or more Exercise questions. Students submit
 * their answers via ExerciseSubmission (one per student per list, enforced
 * by a unique constraint). Optionally tied to a specific Lesson for context.
 *
 * @property int $id
 * @property int $class_id
 * @property int|null $lesson_id Optional link to the lesson this homework relates to
 * @property int $created_by The professor or admin who created this list
 * @property string $title
 * @property string|null $description
 * @property Carbon|null $due_date Null means no deadline
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read TurmaClass $turmaClass
 * @property-read Lesson|null $lesson
 * @property-read User $creator
 * @property-read Collection<int, Exercise> $exercises
 * @property-read Collection<int, ExerciseSubmission> $submissions
 */
class ExerciseList extends Model
{
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'class_id',
        'lesson_id',
        'created_by',
        'title',
        'description',
        'due_date',
        'school_id',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
        ];
    }

    /**
     * The class this exercise list is assigned to.
     */
    public function turmaClass(): BelongsTo
    {
        return $this->belongsTo(TurmaClass::class, 'class_id');
    }

    /**
     * The lesson this exercise list optionally relates to (for context).
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'lesson_id');
    }

    /**
     * The professor or admin who created this exercise list.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Individual questions in this list, ordered by their display position.
     */
    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class)->orderBy('order');
    }

    /**
     * Student submissions for this exercise list (one per student via unique constraint).
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(ExerciseSubmission::class);
    }

    /**
     * Whether the due date has passed. Uses lt(today()) so that a list due
     * today is NOT considered overdue until tomorrow. Lists with no due_date
     * are never overdue.
     */
    public function isOverdue(): bool
    {
        // lt(today()) — a list due today is NOT overdue until tomorrow
        return $this->due_date !== null && $this->due_date->lt(today());
    }
}
