<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\SetTenantContext;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class SetTenantContextTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    private function runMiddleware(?User $user): Response
    {
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        return (new SetTenantContext)->handle($request, fn ($r) => new Response('ok'));
    }

    // ── Binding tests ───────────────────────────────────────────

    public function test_binds_school_id_when_user_has_school(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);

        $this->runMiddleware($user);

        $this->assertTrue(app()->bound('tenant.school_id'));
        $this->assertSame($school->id, app('tenant.school_id'));
    }

    public function test_does_not_bind_when_user_school_id_is_null(): void
    {
        $user = User::factory()->create(['school_id' => null]);

        $this->runMiddleware($user);

        $this->assertFalse(app()->bound('tenant.school_id'));
    }

    public function test_does_not_bind_when_no_authenticated_user(): void
    {
        $this->runMiddleware(null);

        $this->assertFalse(app()->bound('tenant.school_id'));
    }

    // ── Next handler ────────────────────────────────────────────

    public function test_passes_request_to_next_handler(): void
    {
        $user = User::factory()->create();

        $response = $this->runMiddleware($user);

        $this->assertSame('ok', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }

    // ── Isolation ───────────────────────────────────────────────

    public function test_second_request_does_not_bleed_into_first(): void
    {
        $school = School::factory()->create();
        $userWithSchool = User::factory()->create(['school_id' => $school->id]);

        // First request: tenant bound
        $this->runMiddleware($userWithSchool);
        $this->assertTrue(app()->bound('tenant.school_id'));

        // Simulate request lifecycle reset
        app()->forgetInstance('tenant.school_id');

        // Second request: no user (super-admin or guest)
        $this->runMiddleware(null);
        $this->assertFalse(app()->bound('tenant.school_id'));
    }
}
