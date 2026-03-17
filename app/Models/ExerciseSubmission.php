<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function exerciseList(): BelongsTo
    {
        return $this->belongsTo(ExerciseList::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExerciseAnswer::class);
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }
}
