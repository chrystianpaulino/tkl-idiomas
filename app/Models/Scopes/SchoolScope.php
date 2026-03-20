<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Automatically restricts all Eloquent queries to the active tenant's school.
 *
 * Applied via the BelongsToSchool trait. The active school_id is resolved from
 * the 'tenant.school_id' binding in the service container, set by SetTenantContext
 * middleware at the start of each authenticated request.
 *
 * When no tenant is bound (console context, super_admin requests, or unauthenticated),
 * the scope is a no-op and all rows are visible.
 */
class SchoolScope implements Scope
{
    /**
     * Applies the tenant school_id constraint to the query if a tenant context is active.
     *
     * @param  Builder<Model>  $builder  The Eloquent query builder being constrained.
     * @param  Model  $model  The model instance the scope is applied to.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->bound('tenant.school_id') && ($schoolId = app('tenant.school_id')) !== null && $schoolId > 0) {
            $builder->where($model->getTable().'.school_id', app('tenant.school_id'));
        }
    }
}
