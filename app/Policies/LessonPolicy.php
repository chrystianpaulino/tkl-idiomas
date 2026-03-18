<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\TurmaClass;
use App\Models\User;

/**
 * Authorization policy for Lesson records.
 *
 * Lesson creation requires the class context (TurmaClass) to verify the professor
 * teaches that specific class. Deletion allows the professor who taught the lesson
 * or any admin.
 *
 * Registered manually in AppServiceProvider::boot() via Gate::policy().
 */
class LessonPolicy
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
     * Only admins and the assigned professor of the class can register lessons.
     */
    public function create(User $user, TurmaClass $turmaClass): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isProfessor() && $turmaClass->professor_id === $user->id;
    }

    /**
     * Only admins and the professor who conducted the lesson can delete it.
     */
    public function delete(User $user, Lesson $lesson): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isProfessor() && $lesson->professor_id === $user->id;
    }
}
