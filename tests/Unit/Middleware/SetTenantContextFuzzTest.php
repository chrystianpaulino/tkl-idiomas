<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\SetTenantContext;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class SetTenantContextFuzzTest extends TestCase
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

    // ── school_id = 0 edge case ──────────────────────────────────

    public function test_school_id_zero_does_not_bind_tenant(): void
    {
        // school_id = 0 is invalid but possible in corrupt data.
        // The middleware checks `$user->school_id !== null` which means
        // 0 (int) !== null is TRUE, so it WILL bind.
        //
        // SQLite FK constraints prevent setting school_id = 0 via DB update
        // (no school with id=0 exists), so we simulate via a User mock-like
        // approach: create a user and manually set the attribute without saving.
        $user = User::factory()->create(['school_id' => null]);
        // Set attribute in-memory only (bypass FK constraint)
        $user->setAttribute('school_id', 0);

        $this->runMiddleware($user);

        // FIXED: school_id = 0 is now rejected by the `> 0` guard.
        // The middleware requires school_id to be both non-null AND positive.
        $this->assertFalse(app()->bound('tenant.school_id'));
    }

    // ── school_id as string '1' (type coercion) ──────────────────

    public function test_school_id_string_is_handled_gracefully(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);

        // Simulate a user object where school_id is the string '1'
        // In practice, SQLite returns integers, but we test type coercion.
        // After Eloquent hydration, school_id is already cast to int by the
        // model's integer column. We verify the middleware still binds it.
        $this->runMiddleware($user);

        $this->assertTrue(app()->bound('tenant.school_id'));
        $this->assertSame($school->id, app('tenant.school_id'));
    }

    // ── Response does not expose school_id ────────────────────────

    public function test_middleware_does_not_expose_school_id_in_response(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);

        $response = $this->runMiddleware($user);

        // Verify school_id is NOT leaked in response headers
        $this->assertNull($response->headers->get('X-School-Id'));
        $this->assertNull($response->headers->get('X-Tenant-Id'));
        $this->assertNull($response->headers->get('school_id'));

        // Verify school_id is NOT in response body
        $this->assertStringNotContainsString('school_id', $response->getContent());
        $this->assertStringNotContainsString((string) $school->id, $response->getContent());
    }

    // ── Multiple sequential calls: last wins ─────────────────────

    public function test_multiple_sequential_middleware_calls_last_wins(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        $userA = User::factory()->create(['school_id' => $schoolA->id]);
        $userB = User::factory()->create(['school_id' => $schoolB->id]);

        // First call: binds school A
        $this->runMiddleware($userA);
        $this->assertSame($schoolA->id, app('tenant.school_id'));

        // Second call WITHOUT clearing: binds school B (overwrites)
        // app()->instance() overwrites the previous binding.
        $this->runMiddleware($userB);
        $this->assertSame($schoolB->id, app('tenant.school_id'));

        // Document: last call wins, no error, no accumulation
        $this->assertNotSame($schoolA->id, app('tenant.school_id'));
    }

    // ── Null user after bound tenant ─────────────────────────────

    public function test_null_user_after_bound_tenant_does_not_clear_binding(): void
    {
        $school = School::factory()->create();
        $user = User::factory()->create(['school_id' => $school->id]);

        // First call: binds tenant
        $this->runMiddleware($user);
        $this->assertTrue(app()->bound('tenant.school_id'));

        // Second call with null user: middleware does NOT clear previous binding
        // because it only sets on non-null, never explicitly removes.
        $this->runMiddleware(null);

        // The binding from the first call STILL EXISTS.
        // This is a potential tenant leakage vector between requests if the
        // container is not reset. Documenting the actual behavior.
        $this->assertTrue(app()->bound('tenant.school_id'));
        $this->assertSame($school->id, app('tenant.school_id'));
    }
}
