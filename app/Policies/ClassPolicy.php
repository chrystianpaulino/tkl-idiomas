<?php

namespace App\Policies;

use App\Models\TurmaClass;
use App\Models\User;

/**
 * Authorization policy for TurmaClass (teaching groups).
 *
 * View access follows a hierarchical model: admins see everything, professors
 * see their own classes, and students see only classes they are enrolled in.
 * Create/update/delete operations are admin-only.
 *
 * IMPORTANT: This policy must be manually registered in AppServiceProvider::boot()
 * via Gate::policy() -- auto-discovery is NOT active in this project.
 */
class ClassPolicy
{
    /**
     * All authenticated users can see the class listing page.
     * Row-level filtering happens in ClassController::index.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can see class list
    }

    /**
     * Admins see all; professors see their own classes; students see enrolled classes.
     */
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
