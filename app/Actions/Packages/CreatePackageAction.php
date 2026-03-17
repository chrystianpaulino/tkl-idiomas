<?php

namespace App\Actions\Packages;

use App\Models\LessonPackage;
use App\Models\User;

class CreatePackageAction
{
    public function execute(User $student, array $data): LessonPackage
    {
        return LessonPackage::create([
            'student_id' => $student->id,
            'total_lessons' => $data['total_lessons'],
            'expires_at' => $data['expires_at'] ?? null,
        ]);
    }
}
