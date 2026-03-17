<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'exercise_list_id',
        'order',
        'question',
        'type',
    ];

    public function exerciseList(): BelongsTo
    {
        return $this->belongsTo(ExerciseList::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ExerciseAnswer::class);
    }
}
