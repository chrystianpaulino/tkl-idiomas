<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    // Relationships

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function turmaClass(): BelongsTo
    {
        return $this->belongsTo(TurmaClass::class, 'class_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'lesson_id');
    }

    // Status helpers

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

    // Scopes

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('scheduled_at', '>', now())
                     ->where('status', 'scheduled');
    }

    public function scopeForClass(Builder $query, int $classId): Builder
    {
        return $query->where('class_id', $classId);
    }

    public function scopeForStudent(Builder $query, int $studentId): Builder
    {
        return $query->whereHas('turmaClass.students', fn ($q) => $q->where('users.id', $studentId));
    }
}
