<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A teaching group (turma) that brings together one professor and multiple students.
 *
 * Named TurmaClass because 'Class' is a PHP reserved word. Maps to the 'classes'
 * table via an explicit $table override. Serves as the central organizational unit:
 * lessons, materials, schedules, and exercise lists all belong to a TurmaClass.
 *
 * @property int $id
 * @property string $name
 * @property int $professor_id       Foreign key to the User who teaches this class
 * @property string|null $description
 * @property int|null $school_id     Tenant scope
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read User $professor
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $students
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Lesson> $lessons
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Material> $materials
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Schedule> $schedules
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ScheduledLesson> $scheduledLessons
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ExerciseList> $exerciseLists
 */
class TurmaClass extends Model
{
    use HasFactory;

    protected $table = 'classes';

    protected $fillable = [
        'name',
        'professor_id',
        'description',
        'school_id',
    ];

    /**
     * The professor assigned to teach this class.
     */
    public function professor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professor_id');
    }

    /**
     * Students enrolled in this class, via the class_students pivot table.
     *
     * @see EnrollStudentAction   For the enrollment workflow
     * @see UnenrollStudentAction For the unenrollment workflow
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'class_students', 'class_id', 'student_id')
            ->withTimestamps();
    }

    /**
     * Individual lesson records conducted within this class.
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'class_id');
    }

    /**
     * Uploaded teaching materials (PDFs, videos, etc.) shared with students.
     */
    public function materials(): HasMany
    {
        return $this->hasMany(Material::class, 'class_id');
    }

    /**
     * Recurring weekly schedule rules for this class (e.g., "every Monday at 14:00").
     */
    public function schedules(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Schedule::class, 'class_id');
    }

    /**
     * Concrete lesson instances generated from recurring schedules.
     */
    public function scheduledLessons(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ScheduledLesson::class, 'class_id');
    }

    /**
     * Homework / exercise lists assigned to students in this class.
     */
    public function exerciseLists(): HasMany
    {
        return $this->hasMany(ExerciseList::class, 'class_id');
    }
}
