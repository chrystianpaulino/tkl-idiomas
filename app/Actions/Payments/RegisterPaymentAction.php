<?php

namespace App\Actions\Payments;

use App\Models\LessonPackage;
use App\Models\Payment;
use App\Models\User;
use App\Support\Audit;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
     * @param  User  $student  The student making the payment
     * @param  LessonPackage  $package  The package being paid for (must belong to $student)
     * @param  array  $data  Validated data: amount, method, paid_at, notes, currency
     * @param  int  $registeredBy  User ID of the admin recording this payment
     * @return Payment The created payment record
     *
     * @throws \InvalidArgumentException If amount is zero or negative
     * @throws AccessDeniedHttpException If package does not belong to student (abort 403)
     */
    public function execute(User $student, LessonPackage $package, array $data, int $registeredBy): Payment
    {
        if ($package->student_id !== $student->id) {
            abort(403, 'The package does not belong to the given student.');
        }

        if ($data['amount'] <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        // Foreign keys (student_id, lesson_package_id, registered_by) and
        // school_id are intentionally outside Payment::$fillable so they can
        // never be reassigned via mass-assignment. This action is the only
        // writer that may set them.
        $payment = new Payment;
        $payment->student_id = $student->id;
        $payment->lesson_package_id = $package->id;
        $payment->registered_by = $registeredBy;
        $payment->school_id = $package->school_id;
        $payment->amount = $data['amount'];
        $payment->currency = $data['currency'] ?? 'BRL';
        $payment->method = $data['method'] ?? 'pix';
        $payment->paid_at = $data['paid_at'];
        $payment->notes = $data['notes'] ?? null;
        $payment->save();

        Audit::log('payment.registered', [
            'payment_id' => $payment->id,
            'student_id' => $student->id,
            'package_id' => $package->id,
            'school_id' => $payment->school_id,
            'registered_by' => $registeredBy,
            'amount' => (string) $payment->amount,
            'currency' => $payment->currency,
            'method' => $payment->method,
        ]);

        return $payment;
    }
}
