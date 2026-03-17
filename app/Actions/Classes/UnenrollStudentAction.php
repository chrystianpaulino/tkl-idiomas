<?php

namespace App\Actions\Classes;

use App\Models\TurmaClass;
use App\Models\User;

/**
 * Removes a student from a class by detaching them from the class_students pivot.
 *
 * Does not delete any existing lesson records or exercise submissions --
 * those are retained as historical data.
 */
class UnenrollStudentAction
{
    /**
     * @param TurmaClass $turmaClass The class to unenroll the student from
     * @param User       $student    The student to remove
     */
    public function execute(TurmaClass $turmaClass, User $student): void
    {
        $turmaClass->students()->detach($student->id);
    }
}
