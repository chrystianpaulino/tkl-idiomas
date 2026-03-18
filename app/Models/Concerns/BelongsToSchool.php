<?php

namespace App\Models\Concerns;

use App\Models\Scopes\SchoolScope;

/**
 * Provides automatic multi-tenant data isolation for Eloquent models.
 *
 * When applied to a model, this trait:
 * 1. Adds a SchoolScope global scope so all queries are restricted to the active tenant.
 * 2. Automatically sets school_id on new records from the active tenant context,
 *    preventing any record from being created without tenant ownership.
 *
 * Usage: Add `use BelongsToSchool;` to any tenant-scoped model.
 * Tenant context is set by SetTenantContext middleware.
 *
 * To bypass the scope in specific queries (e.g., admin cross-school lookups):
 *   Model::withoutGlobalScope(SchoolScope::class)->get()
 */
trait BelongsToSchool
{
    /**
     * Boots the trait: registers the global scope and the creating event listener.
     */
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new SchoolScope);

        static::creating(function (self $model): void {
            if (($model->school_id === null || $model->school_id === 0) && app()->bound('tenant.school_id')) {
                $model->school_id = app('tenant.school_id');
            }
        });
    }
}
