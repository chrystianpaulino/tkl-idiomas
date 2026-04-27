<?php

namespace App\Policies;

use App\Models\User;
use App\Providers\AppServiceProvider;

/**
 * Centralises authorization rules for User CRUD.
 *
 * NOTE: A global `Gate::before` hook in AppServiceProvider grants every ability
 * to super_admin BEFORE policy methods run -- so super_admin handling is
 * intentionally absent from each method below. Returning false from a policy
 * method does NOT block super_admin (the Gate::before short-circuits first).
 *
 * @see AppServiceProvider::boot() for the super_admin bypass
 */
class UserPolicy
{
    /**
     * View the user listing (Users/Index).
     *
     * Only school administrators see the list (super_admin is implicit).
     * The controller still scopes the query by school_id for school_admin.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    /**
     * View an individual user's profile.
     *
     * - Self-access is always allowed (a user can view themselves).
     * - school_admin can view any user in the same school.
     */
    public function view(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return true;
        }

        return $actor->isAdmin() && $actor->school_id === $target->school_id;
    }

    /**
     * Create a new user.
     *
     * Restricted to school administrators (super_admin via Gate::before).
     */
    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    /**
     * Update an existing user.
     *
     * - Self-edit is always allowed (used by ProfileController for the user's own profile).
     * - school_admin can update users in the same school, but is forbidden
     *   from editing super_admin accounts or other school administrators
     *   (no horizontal privilege escalation between admins).
     */
    public function update(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return true;
        }

        if (! $actor->isAdmin()) {
            return false;
        }

        if ($actor->school_id !== $target->school_id) {
            return false;
        }

        // school_admin cannot edit super_admin or other school_admin/admin accounts.
        // Only professor and aluno may be modified by a school_admin.
        return in_array($target->role, ['professor', 'aluno'], true);
    }

    /**
     * Delete a user.
     *
     * - Cannot delete oneself (prevents an admin locking themselves out).
     * - school_admin can delete only professor/aluno users in the same school.
     *   Deleting another admin (or super_admin) is reserved for super_admin.
     */
    public function delete(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return false;
        }

        if (! $actor->isAdmin()) {
            return false;
        }

        if ($actor->school_id !== $target->school_id) {
            return false;
        }

        return in_array($target->role, ['professor', 'aluno'], true);
    }

    /**
     * Whether the actor may assign the given role to a target user.
     *
     * Auxiliary helper consumed by StoreUserRequest / UpdateUserRequest to
     * compute the allow-list passed to Rule::in(). super_admin (handled by
     * Gate::before) may assign any role; school_admin is restricted to
     * professor / aluno to prevent horizontal privilege escalation.
     */
    public function assignRole(User $actor, string $role): bool
    {
        if (! $actor->isAdmin()) {
            return false;
        }

        return in_array($role, ['professor', 'aluno'], true);
    }

    /**
     * Reissue a pending invite for a user (Wave 9).
     *
     * Mirrors update() permissions exactly: only school administrators can
     * resend, only within their own school, and only for professor/aluno
     * accounts -- never for other admins or super_admin. The action layer
     * additionally rejects already-accepted users; this gate is the access
     * boundary.
     */
    public function resendInvite(User $actor, User $target): bool
    {
        if (! $actor->isAdmin()) {
            return false;
        }

        if ($actor->school_id !== $target->school_id) {
            return false;
        }

        if (! in_array($target->role, ['professor', 'aluno'], true)) {
            return false;
        }

        // Resending for users who already accepted is meaningless and would
        // hand the admin a token to a real account. Block at the gate.
        return $target->hasPendingInvite();
    }
}
