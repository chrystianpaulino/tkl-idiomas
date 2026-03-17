<?php

namespace App\Actions\Payments;

use App\Models\LessonPackage;
use App\Models\Payment;
use App\Models\User;

/**
 * Records a payment for a student's lesson package.
 *
 * Validates that the package belongs to the given student and that the amount
 * is positive. The unique constraint on lesson_package_id at the DB level ensures
 * a package can only be paid once -- duplicate attempts raise a UniqueConstraintViolationException
 * which PaymentController catches and shows a user-friendly error.
 *
 * @see PaymentController::store() For the duplicate payment handling
 */
class RegisterPaymentAction
{
    /**
     * @param User          $student      The student making the payment
     * @param LessonPackage $package      The package being paid for (must belong to $student)
     * @param array         $data         Validated data: amount, method, paid_at, notes, currency
     * @param int           $registeredBy User ID of the admin recording this payment
     * @return Payment                    The created payment record
     *
     * @throws \InvalidArgumentException If amount is zero or negative
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException  If package does not belong to student (abort 403)
     */
    public function execute(User $student, LessonPackage $package, array $data, int $registeredBy): Payment
    {
        if ($package->student_id !== $student->id) {
            abort(403, 'The package does not belong to the given student.');
        }

        if ($data['amount'] <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        return Payment::create([
            'student_id'        => $student->id,
            'lesson_package_id' => $package->id,
            'registered_by'     => $registeredBy,
            'amount'            => $data['amount'],
            'currency'          => $data['currency'] ?? 'BRL',
            'method'            => $data['method'] ?? 'pix',
            'paid_at'           => $data['paid_at'],
            'notes'             => $data['notes'] ?? null,
            'school_id'         => $package->school_id,
        ]);
    }
}
