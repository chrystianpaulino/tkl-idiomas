<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A concrete lesson slot generated from a recurring Schedule rule.
 *
 * Lifecycle: scheduled -> confirmed (via ConfirmScheduledLessonAction, which creates
 * Lesson records for each enrolled student) or scheduled -> cancelled (via
 * CancelScheduledLessonAction). Once confirmed, the lesson_id field links to the
 * first Lesson record created (representative record for group classes).
 *
 * @property int $id
 * @property int $schedule_id
 * @property int $class_id
 * @property \Illuminate\Support\Carbon $scheduled_at     Date and time for this slot
 * @property string $status                               One of: scheduled, confirmed, cancelled
 * @property string|null $cancelled_reason                 Free-text reason when cancelled
 * @property int|null $lesson_id                           Set when confirmed; links to the representative Lesson
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read Schedule $schedule
 * @property-read TurmaClass $turmaClass
 * @property-read Lesson|null $lesson
 *
 * @method static Builder upcoming()                     Future slots still in 'scheduled' status
 * @method static Builder forClass(int $classId)         Filter by class
 * @method static Builder forStudent(int $studentId)     Filter by enrolled student (via class pivot)
 */
class ScheduledLesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_id',
        'class_id',
        'scheduled_at',
        'status',
        'cancelled_reason',
        'lesson_id',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────

    /**
     * The recurring schedule rule that generated this slot.
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * The class this scheduled lesson belongs to.
     */
    public function turmaClass(): BelongsTo
    {
        return $this->belongsTo(TurmaClass::class, 'class_id');
    }

    /**
     * The actual Lesson record created when this slot was confirmed.
     * Only set after ConfirmScheduledLessonAction runs; points to the first
     * lesson created (representative record for group classes).
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'lesson_id');
    }

    // ── Status helpers ────────────────────────────────────────────

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    // ── Scopes ────────────────────────────────────────────────────

    /**
     * Future slots that have not yet been confirmed or cancelled.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('scheduled_at', '>', now())
                     ->where('status', 'scheduled');
    }

    /**
     * Filter scheduled lessons for a specific class.
     */
    public function scopeForClass(Builder $query, int $classId): Builder
    {
        return $query->where('class_id', $classId);
    }

    /**
     * Filter scheduled lessons for classes where a given student is enrolled.
     */
    public function scopeForStudent(Builder $query, int $studentId): Builder
    {
        return $query->whereHas('turmaClass.students', fn ($q) => $q->where('users.id', $studentId));
    }
}
