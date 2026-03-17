<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware that restricts access to users with specific roles.
 *
 * Registered as the 'role' alias in bootstrap/app.php. Applied to route groups
 * to enforce role-based access at the routing layer (first line of defense before
 * FormRequest::authorize and Policy checks).
 *
 * Usage: ->middleware('role:admin,professor')
 *
 * Aborts with 403 if the authenticated user's role is not in the allowed list.
 * Also aborts for unauthenticated users (null role).
 */
class EnsureUserHasRole
{
    /**
     * @param Request  $request
     * @param Closure  $next
     * @param string   ...$roles Allowed role names (e.g., 'admin', 'professor', 'aluno')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!in_array($request->user()?->role, $roles)) {
            abort(403);
        }

        return $next($request);
    }
}
