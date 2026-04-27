<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

/**
 * Inertia middleware that shares global props with all frontend pages.
 *
 * Provides the authenticated user (id, name, email, role), their school context
 * (including white-label logo + theme colors), flash messages (success/error),
 * and the application name. Flash messages use lazy closures to avoid reading
 * from session on every request -- only evaluated when the frontend actually
 * renders the prop.
 *
 * For super_admin users (no school_id) `auth.school` is null. For tenant users
 * the payload always includes a `theme` block with sane defaults, so the frontend
 * can apply CSS variables unconditionally without nil-checking each color.
 *
 * AppLayout on the frontend automatically displays flash.success and flash.error.
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * Hardcoded fallbacks mirror the platform's Tailwind design tokens.
     * Kept in sync with the migration-level column defaults.
     */
    private const DEFAULT_PRIMARY_COLOR = '#4f46e5';   // indigo-600

    private const DEFAULT_SECONDARY_COLOR = '#0f172a'; // slate-900

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
                'school' => fn () => $this->resolveSchoolPayload($request),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'app_name' => config('app.name'),
        ]);
    }

    /**
     * Build the auth.school payload including white-label identity.
     *
     * Returns null when the user is unauthenticated or has no school
     * (super_admin). The shape is stable: id, name, slug, logo_url, theme.
     *
     * @return array{id: int, name: string, slug: string, logo_url: ?string, theme: array{primary: string, secondary: string}}|null
     */
    private function resolveSchoolPayload(Request $request): ?array
    {
        $school = $request->user()?->school;

        if ($school === null) {
            return null;
        }

        return [
            'id' => $school->id,
            'name' => $school->name,
            'slug' => $school->slug,
            'logo_url' => $school->logo_url
                ? Storage::disk('public')->url($school->logo_url)
                : null,
            'theme' => [
                'primary' => $school->primary_color ?: self::DEFAULT_PRIMARY_COLOR,
                'secondary' => $school->secondary_color ?: self::DEFAULT_SECONDARY_COLOR,
            ],
        ];
    }
}
