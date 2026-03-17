<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Inertia middleware that shares global props with all frontend pages.
 *
 * Provides the authenticated user (id, name, email, role), their school context,
 * flash messages (success/error), and the application name. Flash messages use
 * lazy closures to avoid reading from session on every request -- only evaluated
 * when the frontend actually renders the prop.
 *
 * AppLayout on the frontend automatically displays flash.success and flash.error.
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => fn () => $request->user()?->only('id', 'name', 'email', 'role'),
                'school' => fn () => $request->user()?->school?->only('id', 'name', 'slug'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'app_name' => config('app.name'),
        ]);
    }
}
