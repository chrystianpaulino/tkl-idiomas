<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\TurmaClass;
use App\Models\User;

class LessonPolicy
{
    public function create(User $user, TurmaClass $turmaClass): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isProfessor() && $turmaClass->professor_id === $user->id;
    }

    public function delete(User $user, Lesson $lesson): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isProfessor() && $lesson->professor_id === $user->id;
    }
}
