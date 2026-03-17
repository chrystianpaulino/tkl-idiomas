<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TurmaClass extends Model
{
    use HasFactory;

    protected $table = 'classes';

    protected $fillable = [
        'name',
        'professor_id',
        'description',
    ];

    public function professor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professor_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'class_students', 'class_id', 'student_id')
            ->withTimestamps();
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'class_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class, 'class_id');
    }

    public function schedules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Schedule::class, 'class_id');
    }

    public function scheduledLessons(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ScheduledLesson::class, 'class_id');
    }

    public function exerciseLists(): HasMany
    {
        return $this->hasMany(ExerciseList::class, 'class_id');
    }
}
