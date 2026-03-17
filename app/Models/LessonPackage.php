<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student's lesson credit package -- the core billing unit of the platform.
 *
 * A package grants a student N lessons (total_lessons). As lessons are consumed
 * via RegisterLessonAction, used_lessons is atomically incremented. A package is
 * considered "active" when it has remaining credits AND has not expired.
 *
 * IMPORTANT: used_lessons is intentionally excluded from $fillable. It MUST only
 * be modified through RegisterLessonAction (increment) and DeleteLessonAction
 * (decrement) using lockForUpdate() to maintain atomicity under concurrency.
 *
 * @property int $id
 * @property int $student_id
 * @property int|null $school_id                     Tenant scope
 * @property int $total_lessons                      Total credits in this package
 * @property int $used_lessons                       Credits consumed; managed exclusively by RegisterLessonAction/DeleteLessonAction
 * @property string|null $price                      Package price (decimal:2), null if complimentary
 * @property string|null $currency                   ISO 4217 code (e.g., 'BRL')
 * @property \Illuminate\Support\Carbon|null $purchased_at
 * @property \Illuminate\Support\Carbon|null $expires_at  Null means the package never expires
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @property-read int $remaining                     Computed: max(0, total_lessons - used_lessons)
 * @property-read User $student
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Lesson> $lessons
 * @property-read Payment|null $payment
 *
 * @method static \Illuminate\Database\Eloquent\Builder active()          Packages with remaining credits and not expired
 * @method static \Illuminate\Database\Eloquent\Builder needingPayment()  Priced packages without an associated payment
 */
class LessonPackage extends Model
{
    use HasFactory;

    // NOTE: 'used_lessons' is intentionally NOT in $fillable. Modify only via RegisterLessonAction/DeleteLessonAction.
    protected $fillable = [
        'student_id',
        'total_lessons',
        'price',
        'currency',
        'purchased_at',
        'expires_at',
        'school_id',
    ];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'datetime',
            'expires_at' => 'datetime',
            'price' => 'decimal:2',
        ];
    }

    /**
     * Scope to packages that still have available credits and have not expired.
     *
     * A null expires_at means the package never expires. This scope is used by
     * RegisterLessonAction and User::remaining_lessons -- changes here directly
     * affect core billing logic.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereColumn('used_lessons', '<', 'total_lessons')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * How many lesson credits remain in this package.
     */
    public function getRemainingAttribute(): int
    {
        return max(0, $this->total_lessons - $this->used_lessons);
    }

    // ── Status helpers ────────────────────────────────────────────

    /**
     * Whether all credits in this package have been consumed.
     */
    public function isExhausted(): bool
    {
        return $this->used_lessons >= $this->total_lessons;
    }

    /**
     * Whether the package's expiration date has passed. Packages with null
     * expires_at never expire.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Whether this package can still be used to register lessons.
     */
    public function isActive(): bool
    {
        return !$this->isExhausted() && !$this->isExpired();
    }

    // ── Relationships ─────────────────────────────────────────────

    /**
     * The student who owns this lesson credit package.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Lessons that consumed credits from this package. Uses restrictOnDelete
     * at the DB level because lessons are audit records that must not be lost.
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'package_id');
    }

    /**
     * The payment record associated with this package (one-to-one).
     * A package may not have a payment yet if it is unpaid.
     */
    public function payment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Payment::class, 'lesson_package_id');
    }

    /**
     * Whether this package has an associated payment record.
     */
    public function isPaid(): bool
    {
        return $this->payment()->exists();
    }

    /**
     * Scope to packages that have a price set but no payment record yet,
     * indicating the student still owes payment.
     */
    public function scopeNeedingPayment(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNotNull('price')
                     ->whereDoesntHave('payment');
    }
}
