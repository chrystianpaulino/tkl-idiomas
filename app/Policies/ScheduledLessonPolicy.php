<?php

namespace App\Policies;

use App\Models\ScheduledLesson;
use App\Models\User;

/**
 * Authorization policy for ScheduledLesson (concrete calendar slots).
 *
 * View access mirrors SchedulePolicy: admins see all in their school,
 * professors see slots for classes they teach, students see slots for
 * classes they are enrolled in.
 *
 * Confirm and cancel are restricted to school_admin or the assigned
 * professor of the parent TurmaClass. Students never confirm or cancel.
 *
 * IMPORTANT: This policy must be manually registered in AppServiceProvider::boot()
 * via Gate::policy() -- auto-discovery is NOT active in this project.
 */
class ScheduledLessonPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ScheduledLesson $scheduledLesson): bool
    {
        $turmaClass = $scheduledLesson->turmaClass;

        if ($turmaClass === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isProfessor()) {
            return $turmaClass->professor_id === $user->id;
        }

        return $user->enrolledClasses()->where('classes.id', $turmaClass->id)->exists();
    }

    /**
     * Confirming a slot creates Lesson records and consumes package credits
     * via ConfirmScheduledLessonAction. Restricted to admins and the assigned
     * professor of the parent class. Students never confirm.
     */
    public function confirm(User $user, ScheduledLesson $scheduledLesson): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isProfessor()
            && $scheduledLesson->turmaClass?->professor_id === $user->id;
    }

    /**
     * Same rules as confirm.
     */
    public function cancel(User $user, ScheduledLesson $scheduledLesson): bool
    {
        return $this->confirm($user, $scheduledLesson);
    }
}
