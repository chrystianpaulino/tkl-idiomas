<?php

namespace App\Policies;

use App\Models\ExerciseList;
use App\Models\TurmaClass;
use App\Models\User;

class ExerciseListPolicy
{
    public function viewAny(User $user, TurmaClass $turmaClass): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isProfessor() && $turmaClass->professor_id === $user->id) {
            return true;
        }

        return $user->enrolledClasses()->where('classes.id', $turmaClass->id)->exists();
    }

    public function view(User $user, ExerciseList $exerciseList, TurmaClass $turmaClass): bool
    {
        return $this->viewAny($user, $turmaClass);
    }

    public function create(User $user, TurmaClass $turmaClass): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isProfessor() && $turmaClass->professor_id === $user->id;
    }

    public function submit(User $user, ExerciseList $exerciseList, TurmaClass $turmaClass): bool
    {
        if (! $user->isAluno()) {
            return false;
        }

        return $user->enrolledClasses()->where('classes.id', $turmaClass->id)->exists();
    }

    public function delete(User $user, ExerciseList $exerciseList): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $exerciseList->created_by === $user->id;
    }
}
