<?php

namespace App\Policies;

use App\Models\ExerciseList;
use App\Models\TurmaClass;
use App\Models\User;

/**
 * Authorization policy for exercise lists (homework).
 *
 * Viewing and submitting require enrollment in the class. Creating lists is
 * restricted to admins and the class professor. Deletion is allowed for admins
 * and the creator of the list. The 'submit' ability is student-only.
 *
 * Registered manually in AppServiceProvider::boot() via Gate::policy().
 */
class ExerciseListPolicy
{
    /**
     * Admins, the class professor, and enrolled students can see exercise lists.
     */
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

    /**
     * Only students enrolled in the class can submit answers to exercise lists.
     * Professors and admins cannot submit (they review submissions instead).
     */
    public function submit(User $user, ExerciseList $exerciseList, TurmaClass $turmaClass): bool
    {
        if (! $user->isAluno()) {
            return false;
        }

        return $user->enrolledClasses()->where('classes.id', $turmaClass->id)->exists();
    }

    /**
     * Admins or the professor who created the list can delete it.
     */
    public function delete(User $user, ExerciseList $exerciseList): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $exerciseList->created_by === $user->id;
    }
}
