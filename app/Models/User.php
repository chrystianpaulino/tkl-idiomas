<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    // NOTE: 'role' is intentionally NOT in $fillable to prevent privilege escalation.
    protected $fillable = [
        'name',
        'email',
        'password',
        'school_id',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
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

    // Role helpers

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isProfessor(): bool
    {
        return $this->role === 'professor';
    }

    public function isAluno(): bool
    {
        return $this->role === 'aluno';
    }

    // Relationships

    public function school(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function taughtClasses(): HasMany
    {
        return $this->hasMany(TurmaClass::class, 'professor_id');
    }

    public function enrolledClasses(): BelongsToMany
    {
        return $this->belongsToMany(TurmaClass::class, 'class_students', 'student_id', 'class_id')
            ->withTimestamps();
    }

    public function lessonPackages(): HasMany
    {
        return $this->hasMany(LessonPackage::class, 'student_id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'student_id');
    }

    // Accessor: total remaining lessons across all active packages

    // TODO(review): Returns 0 for both "no active packages" and "all credits consumed".
    // Use $this->lessonPackages()->active()->exists() to distinguish. N+1 risk on collections — use withSum() instead. - business-logic-reviewer, 2026-03-12, Severity: Medium
    public function getRemainingLessonsAttribute(): int
    {
        return (int) $this->lessonPackages()
            ->active()
            ->selectRaw('SUM(total_lessons - used_lessons) as total_remaining')
            ->value('total_remaining');
    }

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payment::class, 'student_id');
    }

    public function needsToRenewPackage(): bool
    {
        return ! $this->lessonPackages()->active()->exists();
    }
}
