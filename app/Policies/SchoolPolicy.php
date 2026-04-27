<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;
use App\Providers\AppServiceProvider;

/**
 * Centralises authorization rules for School (tenant) CRUD.
 *
 * NOTE: A global `Gate::before` hook in AppServiceProvider grants every ability
 * to super_admin BEFORE policy methods run -- so super_admin handling is
 * intentionally absent from each method below. school_admin is scoped to a
 * single school (their own), and school deletion is reserved for super_admin
 * (no school can erase itself).
 *
 * @see AppServiceProvider::boot() for the super_admin bypass
 */
class SchoolPolicy
{
    /**
     * View the schools listing.
     *
     * school_admin sees only their own school in the list (the controller
     * filters the query); super_admin sees all (via Gate::before).
     */
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    /**
     * View an individual school.
     *
     * school_admin can view their own school only.
     */
    public function view(User $actor, School $school): bool
    {
        return $actor->isAdmin() && $actor->school_id === $school->id;
    }

    /**
     * Create a new school (provision a new tenant).
     *
     * Reserved for super_admin (handled by Gate::before).
     */
    public function create(User $actor): bool
    {
        return false;
    }

    /**
     * Update an existing school.
     *
     * school_admin can update their own school's branding/details only.
     */
    public function update(User $actor, School $school): bool
    {
        return $actor->isAdmin() && $actor->school_id === $school->id;
    }

    /**
     * Delete a school.
     *
     * Reserved for super_admin -- a school administrator MUST NOT be able to
     * delete the school they administer (catastrophic data loss + cascading
     * removal of all tenant data via School::booted()).
     */
    public function delete(User $actor, School $school): bool
    {
        return false;
    }
}
