<?php

namespace App\Actions\Payments;

use App\Models\LessonPackage;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class RegisterPaymentAction
{
    public function execute(User $student, LessonPackage $package, array $data): Payment
    {
        if ($package->student_id !== $student->id) {
            abort(403, 'The package does not belong to the given student.');
        }

        return Payment::create([
            'student_id'        => $student->id,
            'lesson_package_id' => $package->id,
            'registered_by'     => Auth::id(),
            'amount'            => $data['amount'],
            'currency'          => $data['currency'] ?? 'BRL',
            'method'            => $data['method'] ?? 'pix',
            'paid_at'           => $data['paid_at'],
            'notes'             => $data['notes'] ?? null,
            'school_id'         => $package->school_id,
        ]);
    }
}
