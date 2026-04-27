<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Centralised emitter for security-relevant audit events.
 *
 * Every call writes a structured info-level record to the dedicated `audit`
 * log channel (see config/logging.php). The actor identity, IP, and user
 * agent are auto-captured so call-sites only need to supply the event-specific
 * context. Sensitive keys (password, remember_token) are redacted defensively
 * even if a caller forgets to strip them.
 *
 * Usage example:
 *   Audit::log('user.created', ['target_user_id' => $user->id, 'role' => $user->role]);
 *
 * The audit log is fire-and-forget by design: failures here MUST NOT cascade
 * into business logic. Log::channel() does not throw on misconfiguration --
 * it returns a Logger that fails silently -- so this contract is preserved.
 */
class Audit
{
    /**
     * Keys that must never appear in the audit log under any circumstance.
     * Defensive guard against accidental leakage of credentials/tokens.
     *
     * @var list<string>
     */
    private const REDACTED_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'admin_password',
        'admin_password_confirmation',
    ];

    /**
     * Emit an audit event to the `audit` log channel.
     *
     * @param  string  $event  Dot-namespaced identifier, e.g. 'user.created', 'auth.login.failed'.
     * @param  array<string, mixed>  $context  Event-specific data. Sensitive keys are redacted.
     */
    public static function log(string $event, array $context = []): void
    {
        $user = Auth::user();
        $request = function_exists('request') ? request() : null;

        $payload = array_merge([
            'event' => $event,
            'actor_id' => $user?->id,
            'actor_role' => $user?->role,
            'actor_school_id' => $user?->school_id,
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ], Arr::except($context, self::REDACTED_KEYS));

        Log::channel('audit')->info($event, $payload);
    }
}
