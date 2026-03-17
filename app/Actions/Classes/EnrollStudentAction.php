<?php

namespace App\Actions\Classes;

use App\Models\TurmaClass;
use App\Models\User;

class EnrollStudentAction
{
    public function execute(TurmaClass $turmaClass, User $student): void
    {
        // syncWithoutDetaching is idempotent — safe to call multiple times
        $turmaClass->students()->syncWithoutDetaching([$student->id]);
    }
}
