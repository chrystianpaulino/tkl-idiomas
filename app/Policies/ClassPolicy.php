<?php

namespace App\Policies;

use App\Models\TurmaClass;
use App\Models\User;

class ClassPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can see class list
    }

    public function view(User $user, TurmaClass $turmaClass): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isProfessor() && $turmaClass->professor_id === $user->id) {
            return true;
        }

        return $user->enrolledClasses()->where('classes.id', $turmaClass->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, TurmaClass $turmaClass): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, TurmaClass $turmaClass): bool
    {
        return $user->isAdmin();
    }
}
