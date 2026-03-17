<?php

namespace App\Actions\Classes;

use App\Models\TurmaClass;
use App\Models\User;

class UnenrollStudentAction
{
    public function execute(TurmaClass $turmaClass, User $student): void
    {
        $turmaClass->students()->detach($student->id);
    }
}
