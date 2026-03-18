<?php

namespace Tests\Feature\Tenant;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Integration tests for the multi-tenant middleware stack.
 *
 * These tests verify the FULL request lifecycle: middleware -> route -> controller -> action -> DB,
 * using real HTTP requests via Laravel's test helpers.
 */
#[Group('integration')]
class TenantMiddlewareIntegrationTest extends TestCase
{
    use RefreshDatabase;

    // ── Dashboard access ─────────────────────────────────────────────

    public function test_school_admin_dashboard_loads_successfully(): void
    {
        $school = School::factory()->create();
        $schoolAdmin = User::factory()->create([
            'role' => 'school_admin',
            'school_id' => $school->id,
        ]);

        $response = $this->actingAs($schoolAdmin)->get('/dashboard');

        $response->assertStatus(200);
    }

    // ── Platform route access control ────────────────────────────────

    public function test_super_admin_can_access_platform_routes(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'school_id' => null,
        ]);

        $response = $this->actingAs($superAdmin)->get('/platform/schools');

        $response->assertStatus(200);
    }

    public function test_school_admin_cannot_access_platform_routes(): void
    {
        $school = School::factory()->create();
        $schoolAdmin = User::factory()->create([
            'role' => 'school_admin',
            'school_id' => $school->id,
        ]);

        $response = $this->actingAs($schoolAdmin)->get('/platform/schools');

        $response->assertStatus(403);
    }

    public function test_professor_cannot_access_platform_routes(): void
    {
        $school = School::factory()->create();
        $professor = User::factory()->create([
            'role' => 'professor',
            'school_id' => $school->id,
        ]);

        $response = $this->actingAs($professor)->get('/platform/schools');

        $response->assertStatus(403);
    }

    public function test_aluno_cannot_access_platform_routes(): void
    {
        $school = School::factory()->create();
        $aluno = User::factory()->create([
            'role' => 'aluno',
            'school_id' => $school->id,
        ]);

        $response = $this->actingAs($aluno)->get('/platform/schools');

        $response->assertStatus(403);
    }

    // ── Admin panel access control ───────────────────────────────────

    public function test_school_admin_can_access_admin_panel(): void
    {
        $school = School::factory()->create();
        $schoolAdmin = User::factory()->create([
            'role' => 'school_admin',
            'school_id' => $school->id,
        ]);

        $response = $this->actingAs($schoolAdmin)->get('/admin/users');

        $response->assertStatus(200);
    }

    public function test_old_admin_role_can_still_access_admin_panel(): void
    {
        $school = School::factory()->create();
        $oldAdmin = User::factory()->create([
            'role' => 'admin',
            'school_id' => $school->id,
        ]);

        $response = $this->actingAs($oldAdmin)->get('/admin/users');

        $response->assertStatus(200);
    }

    // ── Unauthenticated access ───────────────────────────────────────

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/users');

        $response->assertRedirect('/login');
    }

    // ── Tenant context binding ───────────────────────────────────────

    public function test_tenant_context_is_bound_during_authenticated_request(): void
    {
        $school = School::factory()->create();
        $schoolAdmin = User::factory()->create([
            'role' => 'school_admin',
            'school_id' => $school->id,
        ]);

        $response = $this->actingAs($schoolAdmin)->get('/dashboard');

        $response->assertStatus(200);

        // After the request, verify the tenant context was bound in the container.
        // The SetTenantContext middleware binds 'tenant.school_id' for users with a school_id.
        $this->assertSame($school->id, app('tenant.school_id'));
    }

    public function test_super_admin_request_has_no_tenant_context(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'school_id' => null,
        ]);

        // super_admin is redirected from /dashboard to /platform/dashboard.
        $response = $this->actingAs($superAdmin)->get('/dashboard');
        $response->assertRedirect('/platform/dashboard');

        // Follow the redirect and confirm the platform dashboard loads.
        $response = $this->actingAs($superAdmin)->get('/platform/dashboard');
        $response->assertStatus(200);

        // Super admin has no school_id, so no tenant context should be bound.
        $this->assertFalse(app()->bound('tenant.school_id'));
    }
}
