<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Records a financial payment made by a student for a lesson package.
 *
 * Each payment is linked to exactly one LessonPackage via a unique constraint
 * on lesson_package_id, enforcing a one-to-one relationship (a package can only
 * be paid once). Created exclusively through RegisterPaymentAction.
 *
 * @property int $id
 * @property int $student_id The student who made the payment
 * @property int $lesson_package_id The package being paid for (unique)
 * @property int $registered_by The admin who recorded this payment
 * @property string $amount Payment amount (decimal:2)
 * @property string $currency ISO 4217 currency code (e.g., 'BRL')
 * @property string $method Payment method: pix, cash, card, transfer, other
 * @property Carbon|null $paid_at When the payment was actually made
 * @property string|null $notes
 * @property int|null $school_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $student
 * @property-read LessonPackage $lessonPackage
 * @property-read User $registeredBy
 */
class Payment extends Model
{
    use BelongsToSchool, HasFactory;

    /**
     * Mass-assignment safe fields only.
     *
     * student_id, lesson_package_id, registered_by and school_id are foreign
     * keys / tenant ownership and must be set explicitly by Action classes
     * (RegisterPaymentAction). Removing them from $fillable prevents an
     * accidental future ->update($validated) from forging the audit trail.
     */
    protected $fillable = [
        'amount',
        'currency',
        'method',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * The student who made this payment.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * The lesson package this payment covers. One-to-one via unique constraint.
     */
    public function lessonPackage(): BelongsTo
    {
        return $this->belongsTo(LessonPackage::class, 'lesson_package_id');
    }

    /**
     * The admin who recorded this payment in the system.
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
