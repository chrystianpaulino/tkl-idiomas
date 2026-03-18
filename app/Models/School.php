<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Multi-tenancy root entity representing a language school on the platform.
 *
 * Every tenant-scoped model (User, LessonPackage, Payment, etc.) references
 * a School via school_id. Global scopes for tenant isolation are NOT yet active,
 * so queries must manually filter by school_id where needed.
 *
 * @property int $id
 * @property string $name Display name of the school
 * @property string $slug URL-safe identifier, unique across the platform
 * @property string|null $email Contact email for the school administration
 * @property bool $active Whether the school is currently active on the platform
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, User> $users
 */
class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'email',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * All users (admins, professors, students) belonging to this school.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Cascade-delete all tenant-scoped records before removing the school.
     *
     * The school_id FK was originally defined with nullOnDelete(), then later
     * made NOT NULL — so SQLite would throw a constraint violation if we just
     * called $school->delete() without clearing children first.
     *
     * Deletion order respects FK dependencies:
     *   scheduled_lessons → exercise_submissions* → exercise_lists
     *   → schedules → payments → lessons → lesson_packages
     *   → materials → classes → users
     * (*exercise_submissions cascade from exercise_list_id and student_id)
     */
    protected static function booted(): void
    {
        static::deleting(function (School $school): void {
            $id = $school->id;

            DB::transaction(function () use ($id): void {
                DB::table('scheduled_lessons')->where('school_id', $id)->delete();
                // exercise_submissions cascade when their exercise_list or student is deleted
                DB::table('exercise_lists')->where('school_id', $id)->delete();
                DB::table('schedules')->where('school_id', $id)->delete();
                DB::table('payments')->where('school_id', $id)->delete();
                DB::table('lessons')->where('school_id', $id)->delete();
                DB::table('lesson_packages')->where('school_id', $id)->delete();
                DB::table('materials')->where('school_id', $id)->delete();
                DB::table('classes')->where('school_id', $id)->delete();
                // Users have nullable school_id; cascade via student_id is already handled above.
                DB::table('users')->where('school_id', $id)->delete();
            });
        });
    }
}
