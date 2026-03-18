<?php

namespace App\Policies;

use App\Models\Material;
use App\Models\TurmaClass;
use App\Models\User;

/**
 * Authorization policy for teaching materials.
 *
 * Upload (create) is restricted to admins and the class professor.
 * Deletion is allowed for admins and the original uploader.
 * Download access mirrors class view permissions: admins, the class professor,
 * and enrolled students can all download.
 *
 * Registered manually in AppServiceProvider::boot() via Gate::policy().
 */
class MaterialPolicy
{
    /**
     * Super-admins bypass all policy checks for cross-school management.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Only admins and the class professor can upload materials.
     */
    public function create(User $user, TurmaClass $turmaClass): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isProfessor() && $turmaClass->professor_id === $user->id;
    }

    /**
     * Admins or the original uploader can delete a material.
     */
    public function delete(User $user, Material $material): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $material->uploaded_by === $user->id;
    }

    /**
     * Admins, the class professor, and enrolled students can download materials.
     */
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
