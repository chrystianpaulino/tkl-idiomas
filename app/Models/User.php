<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * Represents any person using the platform: admin, professor, or student (aluno).
 *
 * The user's role determines their permissions throughout the system. Authorization
 * is enforced at three layers: EnsureUserHasRole middleware on route groups,
 * authorize() inside FormRequests, and Policies via $this->authorize() in Controllers.
 *
 * IMPORTANT: The 'role' column is intentionally excluded from $fillable to prevent
 * mass-assignment privilege escalation. Role assignment MUST go through direct
 * attribute setting in CreateUserAction or UserController::update.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $role One of: 'super_admin', 'school_admin', 'admin', 'professor', 'aluno'
 * @property int|null $school_id Tenant scope; null for legacy/unscoped users
 * @property string|null $phone
 * @property Carbon|null $email_verified_at
 * @property string|null $remember_token
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read int $remaining_lessons           Sum of remaining credits across all active packages
 * @property-read School|null $school
 * @property-read Collection<int, TurmaClass> $taughtClasses
 * @property-read Collection<int, TurmaClass> $enrolledClasses
 * @property-read Collection<int, LessonPackage> $lessonPackages
 * @property-read Collection<int, Lesson> $lessons
 * @property-read Collection<int, Payment> $payments
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * NOTE: 'role' and 'school_id' are intentionally NOT in $fillable to prevent
     * privilege escalation and unauthorized tenant reassignment. Both must be set
     * via direct attribute assignment in action classes.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => 'string',
        ];
    }

    // ── Role helpers ──────────────────────────────────────────────

    /**
     * Whether this user is a platform-level super administrator (no school scope).
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Whether this user is a school-level administrator (scoped to one school).
     */
    public function isSchoolAdmin(): bool
    {
        return $this->role === 'school_admin';
    }

    /**
     * Whether this user has administrative privileges.
     *
     * Returns true for both the legacy 'admin' role AND the new 'school_admin' role
     * to maintain backward compatibility during the SaaS migration.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'school_admin']);
    }

    public function isProfessor(): bool
    {
        return $this->role === 'professor';
    }

    public function isAluno(): bool
    {
        return $this->role === 'aluno';
    }

    // ── Relationships ─────────────────────────────────────────────

    /**
     * The school (tenant) this user belongs to.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Classes where this user is the assigned professor.
     */
    public function taughtClasses(): HasMany
    {
        return $this->hasMany(TurmaClass::class, 'professor_id');
    }

    /**
     * Classes where this user is enrolled as a student, via the class_students pivot.
     */
    public function enrolledClasses(): BelongsToMany
    {
        return $this->belongsToMany(TurmaClass::class, 'class_students', 'student_id', 'class_id')
            ->withTimestamps();
    }

    /**
     * Lesson credit packages purchased by this student.
     */
    public function lessonPackages(): HasMany
    {
        return $this->hasMany(LessonPackage::class, 'student_id');
    }

    /**
     * Individual lesson records for this student (both completed and scheduled).
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'student_id');
    }

    /**
     * Aggregate remaining lesson credits across all active (non-exhausted, non-expired) packages.
     *
     * Returns 0 for both "no active packages" and "all credits consumed".
     * Use needsToRenewPackage() to distinguish between the two cases.
     *
     * @see LessonPackage::scopeActive() Defines what makes a package "active"
     */
    // TODO(review): Returns 0 for both "no active packages" and "all credits consumed".
    // Use $this->lessonPackages()->active()->exists() to distinguish. N+1 risk on collections — use withSum() instead. - business-logic-reviewer, 2026-03-12, Severity: Medium
    public function getRemainingLessonsAttribute(): int
    {
        return (int) $this->lessonPackages()
            ->active()
            ->selectRaw('SUM(total_lessons - used_lessons) as total_remaining')
            ->value('total_remaining');
    }

    /**
     * Payment records where this user is the paying student.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'student_id');
    }

    /**
     * Whether the student has no active lesson packages and needs to purchase/renew one
     * before they can attend more lessons.
     */
    public function needsToRenewPackage(): bool
    {
        return ! $this->lessonPackages()->active()->exists();
    }
}
