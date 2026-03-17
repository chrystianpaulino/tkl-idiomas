<?php

namespace App\Actions\Classes;

use App\Models\TurmaClass;
use App\Models\User;

/**
 * Enrolls a student into a class via the class_students pivot table.
 *
 * Uses syncWithoutDetaching for idempotency -- calling this multiple times
 * for the same student-class pair is safe and has no side effects.
 */
class EnrollStudentAction
{
    /**
     * @param TurmaClass $turmaClass The class to enroll the student into
     * @param User       $student    The student to enroll (must have role 'aluno')
     */
    public function execute(TurmaClass $turmaClass, User $student): void
    {
        // syncWithoutDetaching is idempotent — safe to call multiple times
        $turmaClass->students()->syncWithoutDetaching([$student->id]);
    }
}
