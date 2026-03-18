<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the authenticated user's school as the active tenant context.
 *
 * This middleware runs after authentication and resolves the active school_id
 * from the authenticated user's profile. It registers the value as
 * 'tenant.school_id' in the service container, which is consumed by
 * SchoolScope to automatically filter all Eloquent queries.
 *
 * Super-admin users (school_id = null) do not activate a tenant context,
 * so their queries see data across all schools.
 *
 * This middleware must be registered AFTER the 'auth' middleware in the
 * middleware stack so that $request->user() is available.
 */
class SetTenantContext
{
    /**
     * Binds the current user's school_id into the service container.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  Closure  $next  The next middleware handler.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->school_id !== null && $user->school_id > 0) {
            app()->instance('tenant.school_id', $user->school_id);
            Log::debug('Tenant context bound', ['school_id' => $user->school_id, 'user_id' => $user->id]);
        } else {
            Log::debug('No tenant context — super-admin or unauthenticated', ['user_id' => $user?->id]);
        }

        return $next($request);
    }
}
