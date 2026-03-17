<?php

namespace App\Policies;

use App\Models\Material;
use App\Models\TurmaClass;
use App\Models\User;

class MaterialPolicy
{
    public function create(User $user, TurmaClass $turmaClass): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isProfessor() && $turmaClass->professor_id === $user->id;
    }

    public function delete(User $user, Material $material): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $material->uploaded_by === $user->id;
    }

    public function download(User $user, Material $material): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isProfessor() && $material->turmaClass->professor_id === $user->id) {
            return true;
        }

        return $user->enrolledClasses()->where('classes.id', $material->class_id)->exists();
    }
}
