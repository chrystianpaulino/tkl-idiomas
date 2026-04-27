<?php

namespace App\Providers;

use App\Support\Audit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Wires authentication event listeners that emit audit log entries.
 *
 * Laravel 11+ stopped auto-registering this provider, so it is added
 * explicitly to bootstrap/providers.php. Listeners here are intentionally
 * inline closures: each one is a tiny fire-and-forget bridge to the audit
 * channel and does not warrant the ceremony of a dedicated class.
 *
 * Audit failures must never break authentication, so each closure is
 * wrapped in a try/catch that downgrades any logging error to the default
 * application log -- the user's request continues regardless.
 *
 * @see Audit
 */
class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            try {
                Audit::log('auth.login.success', [
                    'user_id' => $event->user->getAuthIdentifier(),
                    'email' => $event->user->getAttribute('email'),
                    'guard' => $event->guard,
                    'remember' => $event->remember,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to write audit entry for auth.login.success', ['error' => $e->getMessage()]);
            }
        });

        Event::listen(Failed::class, function (Failed $event): void {
            try {
                Audit::log('auth.login.failed', [
                    'email' => $event->credentials['email'] ?? null,
                    'guard' => $event->guard,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to write audit entry for auth.login.failed', ['error' => $e->getMessage()]);
            }
        });

        Event::listen(Logout::class, function (Logout $event): void {
            try {
                Audit::log('auth.logout', [
                    'user_id' => $event->user?->getAuthIdentifier(),
                    'guard' => $event->guard,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to write audit entry for auth.logout', ['error' => $e->getMessage()]);
            }
        });

        Event::listen(Lockout::class, function (Lockout $event): void {
            try {
                Audit::log('auth.lockout', [
                    'email' => $event->request->input('email'),
                    'ip' => $event->request->ip(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to write audit entry for auth.lockout', ['error' => $e->getMessage()]);
            }
        });
    }
}
