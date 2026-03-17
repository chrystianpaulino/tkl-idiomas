<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExerciseList extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'lesson_id',
        'created_by',
        'title',
        'description',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
        ];
    }

    public function turmaClass(): BelongsTo
    {
        return $this->belongsTo(TurmaClass::class, 'class_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'lesson_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class)->orderBy('order');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ExerciseSubmission::class);
    }

    public function isOverdue(): bool
    {
        // lt(today()) — a list due today is NOT overdue until tomorrow
        return $this->due_date !== null && $this->due_date->lt(today());
    }
}
