<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An individual lesson record -- the audit trail of a credit being consumed.
 *
 * Created by RegisterLessonAction (which atomically increments the parent package's
 * used_lessons). Lessons use restrictOnDelete on the package_id FK, meaning a
 * LessonPackage cannot be deleted while it has associated lessons. This preserves
 * the billing audit trail.
 *
 * Statuses: 'completed', 'scheduled', 'cancelled', 'absent_excused', 'absent_unexcused'.
 *
 * @property int $id
 * @property int $class_id
 * @property int $student_id
 * @property int $professor_id
 * @property int $package_id The LessonPackage that funded this lesson
 * @property string $title
 * @property string|null $notes
 * @property Carbon|null $conducted_at When the lesson actually took place
 * @property string $status One of: completed, scheduled, cancelled, absent_excused, absent_unexcused
 * @property Carbon|null $scheduled_at Planned date/time (for future lessons)
 * @property int|null $school_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read TurmaClass $turmaClass
 * @property-read User $student
 * @property-read User $professor
 * @property-read LessonPackage $package
 *
 * @method static \Illuminate\Database\Eloquent\Builder upcoming() Future lessons with 'scheduled' status
 * @method static \Illuminate\Database\Eloquent\Builder completed() Lessons with 'completed' status
 */
class Lesson extends Model
{
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'class_id',
        'student_id',
        'professor_id',
        'package_id',
        'title',
        'notes',
        'conducted_at',
        'status',
        'scheduled_at',
        'school_id',
    ];

    protected function casts(): array
    {
        return [
            'conducted_at' => 'datetime',
            'status' => 'string',
            'scheduled_at' => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────

    /**
     * The class in which this lesson was conducted.
     */
    public function turmaClass(): BelongsTo
    {
        return $this->belongsTo(TurmaClass::class, 'class_id');
    }

    /**
     * The student who attended (or was scheduled to attend) this lesson.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * The professor who conducted this lesson.
     */
    public function professor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professor_id');
    }

    /**
     * The lesson package whose credit was consumed for this lesson.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(LessonPackage::class, 'package_id');
    }

    // ── Status helpers ────────────────────────────────────────────

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Whether the student was absent (excused or unexcused). Both statuses
     * currently consume a lesson credit.
     *
     * @see DeleteLessonAction::CREDIT_CONSUMING_STATUSES
     */
    public function isAbsent(): bool
    {
        return in_array($this->status, ['absent_excused', 'absent_unexcused']);
    }

    // ── Scopes ────────────────────────────────────────────────────

    /**
     * Lessons scheduled in the future that have not yet been conducted or cancelled.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('scheduled_at', '>', now())
            ->where('status', 'scheduled');
    }

    /**
     * Lessons that have been successfully conducted.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }
}
