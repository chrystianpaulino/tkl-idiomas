<?php

namespace App\Actions\Packages;

use App\Models\LessonPackage;
use App\Models\User;

/**
 * Creates a new lesson credit package for a student.
 *
 * The package starts with used_lessons = 0 (DB default). A null expires_at
 * means the package never expires. Price and currency are not set here --
 * they can be updated separately or set via RegisterPaymentAction.
 *
 * @see RegisterLessonAction For consuming credits from this package
 */
class CreatePackageAction
{
    /**
     * @param User  $student The student who will own this package
     * @param array $data    Validated data: total_lessons (required), expires_at (optional)
     * @return LessonPackage The newly created package with 0 used lessons
     */
    public function execute(User $student, array $data): LessonPackage
    {
        return LessonPackage::create([
            'student_id' => $student->id,
            'total_lessons' => $data['total_lessons'],
            'expires_at' => $data['expires_at'] ?? null,
        ]);
    }
}
