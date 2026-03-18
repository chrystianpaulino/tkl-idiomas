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
 * A recurring weekly schedule rule for a class (e.g., "every Monday at 14:00 for 60 min").
 *
 * Schedules define the recurrence pattern; concrete lesson slots are materialized
 * as ScheduledLesson records by GenerateScheduledLessonsAction. Deactivating a
 * schedule (active = false) stops future slot generation but does not affect
 * already-generated slots.
 *
 * @property int $id
 * @property int $class_id
 * @property int $weekday 0 = Sunday, 1 = Monday, ..., 6 = Saturday
 * @property string $start_time HH:MM or HH:MM:SS format
 * @property int $duration_minutes Duration of each lesson (default 60)
 * @property bool $active Whether this schedule should generate new slots
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read TurmaClass $turmaClass
 * @property-read Collection<int, ScheduledLesson> $scheduledLessons
 */
class Schedule extends Model
{
    use BelongsToSchool, HasFactory;

    protected $fillable = [
        'class_id',
        'weekday',
        'start_time',
        'duration_minutes',
        'active',
        'school_id',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'weekday' => 'integer',
            'duration_minutes' => 'integer',
        ];
    }

    /**
     * The class this recurring schedule belongs to.
     */
    public function turmaClass(): BelongsTo
    {
        return $this->belongsTo(TurmaClass::class, 'class_id');
    }

    /**
     * Concrete lesson instances generated from this recurring rule.
     */
    public function scheduledLessons(): HasMany
    {
        return $this->hasMany(ScheduledLesson::class);
    }

    /**
     * Return the weekday name in PT-BR (e.g., 'Segunda' for Monday).
     *
     * @return string PT-BR day name, or '?' if the weekday value is out of range
     */
    public function weekdayName(): string
    {
        return ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'][$this->weekday] ?? '?';
    }
}
