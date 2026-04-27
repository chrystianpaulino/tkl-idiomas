<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\SecureHeaders;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

/**
 * Verifies the SecureHeaders middleware emits defence-in-depth headers on
 * every response in the web group.
 *
 * The middleware sits at the tail of the web stack so headers are present on
 * authenticated dashboards as well as redirects and error responses.
 */
class SecureHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    private function authedUser(): User
    {
        $school = School::factory()->create();

        return User::factory()->create([
            'role' => 'school_admin',
            'school_id' => $school->id,
        ]);
    }

    public function test_dashboard_response_has_x_content_type_options_nosniff(): void
    {
        $response = $this->actingAs($this->authedUser())->get('/dashboard');

        $response->assertOk();
        $this->assertSame(
            'nosniff',
            $response->headers->get('X-Content-Type-Options'),
            'Missing or wrong X-Content-Type-Options header'
        );
    }

    public function test_dashboard_response_has_x_frame_options_sameorigin(): void
    {
        $response = $this->actingAs($this->authedUser())->get('/dashboard');

        $response->assertOk();
        $this->assertSame(
            'SAMEORIGIN',
            $response->headers->get('X-Frame-Options'),
            'Missing or wrong X-Frame-Options header'
        );
    }

    public function test_dashboard_response_has_referrer_policy_same_origin(): void
    {
        $response = $this->actingAs($this->authedUser())->get('/dashboard');

        $response->assertOk();
        $this->assertSame(
            'same-origin',
            $response->headers->get('Referrer-Policy'),
            'Missing or wrong Referrer-Policy header'
        );
    }

    public function test_dashboard_response_has_csp_with_default_self(): void
    {
        $response = $this->actingAs($this->authedUser())->get('/dashboard');

        $response->assertOk();
        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp, 'Content-Security-Policy header missing');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
    }

    public function test_https_response_has_hsts(): void
    {
        // Drive the middleware directly with an https Request so $request->secure()
        // returns true. The full HTTP test client does not honour the HTTPS server
        // variable, so we exercise the unit-level branch here.
        $middleware = new SecureHeaders;

        $request = Request::create('https://example.test/dashboard', 'GET');
        $this->assertTrue($request->secure(), 'fixture must be HTTPS');

        $response = $middleware->handle($request, fn () => new Response('ok', 200));

        $hsts = $response->headers->get('Strict-Transport-Security');

        $this->assertNotNull($hsts, 'Strict-Transport-Security must be present on HTTPS responses');
        $this->assertStringContainsString('max-age=31536000', $hsts);
        $this->assertStringContainsString('includeSubDomains', $hsts);
    }

    public function test_http_response_does_not_have_hsts(): void
    {
        // Plain HTTP must not emit HSTS — emitting on http is per-spec ignored
        // and risks accidentally locking dev workflows out of the local site.
        $response = $this->actingAs($this->authedUser())->get('/dashboard');

        $response->assertOk();
        $this->assertNull(
            $response->headers->get('Strict-Transport-Security'),
            'HSTS must not be sent on plain HTTP responses'
        );
    }
}
