<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for SchoolController::store() wired to ProvisionSchoolAction.
 *
 * Covers TKL-001: SchoolController wired to ProvisionSchoolAction.
 */
class SchoolControllerStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    private function superAdmin(): User
    {
        $user = new User;
        $user->name = 'Super Admin';
        $user->email = 'super@test.com';
        $user->password = bcrypt('password');
        $user->role = 'super_admin';
        $user->school_id = null;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function schoolAdmin(): User
    {
        $school = School::factory()->create();
        $user = new User;
        $user->name = 'School Admin';
        $user->email = 'schooladmin@test.com';
        $user->password = bcrypt('password');
        $user->role = 'school_admin';
        $user->school_id = $school->id;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nova Escola',
            'slug' => 'nova-escola',
            'email' => 'contato@novaescola.com',
            'admin_name' => 'Admin Nova',
            'admin_email' => 'admin@novaescola.com',
            'admin_password' => 'secret1234',
            'admin_password_confirmation' => 'secret1234',
        ], $overrides);
    }

    // ── store() creates both school and admin ────────────────────

    public function test_store_creates_school_and_admin_in_database(): void
    {
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)
            ->post(route('platform.schools.store'), $this->validPayload());

        $response->assertRedirect(route('platform.schools.index'));

        $this->assertDatabaseHas('schools', [
            'name' => 'Nova Escola',
            'slug' => 'nova-escola',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@novaescola.com',
            'role' => 'school_admin',
        ]);

        // Verify the admin belongs to the newly created school
        $school = School::where('slug', 'nova-escola')->first();
        $admin = User::where('email', 'admin@novaescola.com')->first();
        $this->assertSame($school->id, $admin->school_id);
    }

    // ── store() flashes success with school name and admin email ─

    public function test_store_flashes_success_message_with_school_name_and_admin_email(): void
    {
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)
            ->post(route('platform.schools.store'), $this->validPayload([
                'name' => 'Escola Sucesso',
                'slug' => 'escola-sucesso',
                'admin_email' => 'admin@sucesso.com',
            ]));

        $response->assertRedirect(route('platform.schools.index'));
        $response->assertSessionHas('success');

        $flash = session('success');
        $this->assertStringContainsString('Escola Sucesso', $flash);
        $this->assertStringContainsString('admin@sucesso.com', $flash);
    }

    // ── StoreSchoolRequest validation: admin_email unique ────────

    public function test_store_rejects_duplicate_admin_email(): void
    {
        $superAdmin = $this->superAdmin();

        // Pre-create a user with same email
        User::factory()->create(['email' => 'existing@test.com']);

        $response = $this->actingAs($superAdmin)
            ->post(route('platform.schools.store'), $this->validPayload([
                'admin_email' => 'existing@test.com',
            ]));

        $response->assertSessionHasErrors('admin_email');
    }

    // ── StoreSchoolRequest validation: admin_password min 8 chars

    public function test_store_rejects_short_admin_password(): void
    {
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)
            ->post(route('platform.schools.store'), $this->validPayload([
                'admin_password' => 'short',
            ]));

        $response->assertSessionHasErrors('admin_password');
    }

    public function test_store_accepts_password_exactly_8_chars(): void
    {
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)
            ->post(route('platform.schools.store'), $this->validPayload([
                'admin_password' => '12345678',
                'admin_password_confirmation' => '12345678',
            ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('platform.schools.index'));
    }

    // ── Authorization: only super_admin may call store ───────────

    public function test_store_returns_403_for_school_admin_via_admin_route(): void
    {
        $schoolAdmin = $this->schoolAdmin();

        // school_admin hitting admin.schools.store gets 403 from StoreSchoolRequest::authorize()
        $response = $this->actingAs($schoolAdmin)
            ->post(route('admin.schools.store'), $this->validPayload());

        $response->assertStatus(403);
    }

    public function test_store_returns_403_for_professor_via_admin_route(): void
    {
        $school = School::factory()->create();
        $professor = new User;
        $professor->name = 'Prof';
        $professor->email = 'prof@test.com';
        $professor->password = bcrypt('password');
        $professor->role = 'professor';
        $professor->school_id = $school->id;
        $professor->email_verified_at = now();
        $professor->save();

        // professor is blocked by role middleware before reaching controller
        $response = $this->actingAs($professor)
            ->post(route('admin.schools.store'), $this->validPayload());

        $response->assertStatus(403);
    }

    public function test_platform_store_returns_403_for_school_admin(): void
    {
        $schoolAdmin = $this->schoolAdmin();

        // school_admin cannot access the platform route (role:super_admin middleware)
        $response = $this->actingAs($schoolAdmin)
            ->post(route('platform.schools.store'), $this->validPayload());

        $response->assertStatus(403);
    }

    public function test_store_redirects_unauthenticated_user_to_login(): void
    {
        $response = $this->post(route('platform.schools.store'), $this->validPayload());

        $response->assertRedirect(route('login'));
    }

    // ── Edge cases ───────────────────────────────────────────────

    public function test_store_rejects_missing_required_fields(): void
    {
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)
            ->post(route('platform.schools.store'), []);

        $response->assertSessionHasErrors(['name', 'slug', 'admin_name', 'admin_email', 'admin_password']);
    }

    public function test_store_rejects_invalid_slug_format(): void
    {
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)
            ->post(route('platform.schools.store'), $this->validPayload([
                'slug' => 'Invalid Slug With Spaces!',
            ]));

        $response->assertSessionHasErrors('slug');
    }

    public function test_store_rejects_duplicate_school_slug(): void
    {
        $superAdmin = $this->superAdmin();

        School::factory()->create(['slug' => 'existing-slug']);

        $response = $this->actingAs($superAdmin)
            ->post(route('platform.schools.store'), $this->validPayload([
                'slug' => 'existing-slug',
            ]));

        $response->assertSessionHasErrors('slug');
    }
}
