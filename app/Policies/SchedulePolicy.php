<?php

namespace App\Policies;

use App\Models\Schedule;
use App\Models\User;

/**
 * Authorization policy for Schedule (recurring weekly schedule rules).
 *
 * Schedule belongs to a TurmaClass, which carries the professor assignment
 * and tenant scope. Authorization mirrors ClassPolicy: school admins manage
 * all schedules of their school, professors manage schedules of classes they
 * teach, and students may only view (read-only) schedules of classes they are
 * enrolled in.
 *
 * IMPORTANT: This policy must be manually registered in AppServiceProvider::boot()
 * via Gate::policy() -- auto-discovery is NOT active in this project.
 */
class SchedulePolicy
{
    /**
     * All authenticated users may reach the schedules listing.
     * Row-level filtering happens in ScheduleController::index.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Admins and professors of the parent class may view; students may view
     * schedules of classes where they are enrolled.
     */
    public function view(User $user, Schedule $schedule): bool
    {
        $turmaClass = $schedule->turmaClass;

        if ($turmaClass === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isProfessor()) {
            return $turmaClass->professor_id === $user->id;
        }

        // Aluno: only schedules from classes where they're enrolled
        return $user->enrolledClasses()->where('classes.id', $turmaClass->id)->exists();
    }

    /**
     * Both school admins and professors may create recurring schedules.
     * Per-class authorization is enforced in StoreScheduleRequest (the
     * professor must own the target class).
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isProfessor();
    }

    /**
     * Admins update any schedule in their school; professors only schedules
     * for classes they teach.
     */
    public function update(User $user, Schedule $schedule): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isProfessor() && $schedule->turmaClass?->professor_id === $user->id;
    }

    /**
     * Same rules as update.
     */
    public function delete(User $user, Schedule $schedule): bool
    {
        return $this->update($user, $schedule);
    }
}
