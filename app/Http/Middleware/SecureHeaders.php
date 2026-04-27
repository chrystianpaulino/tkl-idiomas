<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds defence-in-depth security headers to every web response.
 *
 * Coverage:
 *  - X-Content-Type-Options: nosniff      Stops MIME-type confusion attacks.
 *  - X-Frame-Options: SAMEORIGIN          Blocks clickjacking via cross-origin <iframe>.
 *  - Referrer-Policy: same-origin         Prevents referrer leakage to third parties.
 *  - Content-Security-Policy              Mitigates XSS by restricting script sources.
 *  - Strict-Transport-Security            Only over HTTPS — refuses unencrypted downgrades
 *                                         on subsequent visits for one year.
 *
 * The CSP is intentionally permissive on script-src ('unsafe-inline'/'unsafe-eval')
 * because Inertia + Vite HMR rely on inline scripts and dev-time eval. style-src
 * also permits 'unsafe-inline' for runtime-injected styles (Tailwind/Inertia).
 * Tightening these to 'self' + nonce/hash is a follow-up after auditing all
 * inline blocks — the current values keep the security model strictly tighter
 * than today (no CSP at all) without breaking the development experience.
 */
class SecureHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'same-origin',
            'Content-Security-Policy' => $this->buildCsp(),
        ];

        // HSTS only on HTTPS — emitting it on plain http would either be
        // ignored (per spec) or, worse, cause a browser to refuse the site
        // when developing locally over http after one accidental https visit.
        if ($request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    /**
     * Build the Content-Security-Policy header value.
     *
     * Allows: own origin, dev-time Vite/HMR endpoints (localhost / 127.0.0.1
     * over http/ws), inline scripts/styles required by Inertia, data: URIs for
     * fonts and base64-encoded images, https: for remote images (school logos
     * delivered through CDNs, etc.).
     */
    private function buildCsp(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' http://localhost:* http://127.0.0.1:*",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self' http://localhost:* http://127.0.0.1:* ws://localhost:* ws://127.0.0.1:*",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
    }
}
