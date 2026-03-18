<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Reorder: remove SubstituteBindings from its default position in the
        // web group, then append SetTenantContext → SubstituteBindings so that
        // the tenant scope is active when route model binding resolves models.
        // Without this, the SchoolScope global scope would not filter during
        // route model binding because SubstituteBindings ran before the tenant
        // context was bound.
        $middleware->web(
            remove: [SubstituteBindings::class],
            append: [
                SetTenantContext::class,
                SubstituteBindings::class,
                HandleInertiaRequests::class,
                AddLinkHeadersForPreloadedAssets::class,
            ],
        );

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'tenant' => SetTenantContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
