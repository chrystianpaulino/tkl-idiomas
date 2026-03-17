<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lesson extends Model
{
    use HasFactory;

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

    public function turmaClass(): BelongsTo
    {
        return $this->belongsTo(TurmaClass::class, 'class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professor_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(LessonPackage::class, 'package_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isAbsent(): bool
    {
        return in_array($this->status, ['absent_excused', 'absent_unexcused']);
    }

    public function scopeUpcoming(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('scheduled_at', '>', now())
                     ->where('status', 'scheduled');
    }

    public function scopeCompleted(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'completed');
    }
}
