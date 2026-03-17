<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'weekday',
        'start_time',
        'duration_minutes',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active'           => 'boolean',
            'weekday'          => 'integer',
            'duration_minutes' => 'integer',
        ];
    }

    public function turmaClass(): BelongsTo
    {
        return $this->belongsTo(TurmaClass::class, 'class_id');
    }

    public function scheduledLessons(): HasMany
    {
        return $this->hasMany(ScheduledLesson::class);
    }

    public function weekdayName(): string
    {
        return ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'][$this->weekday] ?? '?';
    }
}
