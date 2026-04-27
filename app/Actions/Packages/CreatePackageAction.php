<?php

namespace App\Actions\Packages;

use App\Models\LessonPackage;
use App\Models\User;

/**
 * Creates a new lesson credit package for a student.
 *
 * The package starts with used_lessons = 0 (DB default). A null expires_at
 * means the package never expires. Price and currency are captured at sale
 * time and stored on the package so reporting can reconcile against the
 * eventual Payment.amount (which the admin may set differently for partial
 * payments or discounts).
 *
 * @see RegisterLessonAction   For consuming credits from this package
 * @see RegisterPaymentAction  For recording the financial settlement against this package
 */
class CreatePackageAction
{
    /**
     * @param  User  $student  The student who will own this package
     * @param  array  $data  Validated data: total_lessons, price, currency (required); expires_at (optional)
     * @return LessonPackage The newly created package with 0 used lessons
     */
    public function execute(User $student, array $data): LessonPackage
    {
        return LessonPackage::create([
            'student_id' => $student->id,
            'total_lessons' => $data['total_lessons'],
            'price' => $data['price'],
            'currency' => $data['currency'],
            'expires_at' => $data['expires_at'] ?? null,
            'school_id' => $student->school_id,
        ]);
    }
}
