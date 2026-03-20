<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for school provisioning via SchoolController::store().
 *
 * Verifies ProvisionSchoolAction is wired correctly and creates both
 * a School and its first school_admin atomically.
 */
class SchoolControllerProvisionTest extends TestCase
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
        $user->email = 'super@provision-test.com';
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
        $user->email = 'schooladmin@provision-test.com';
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
            'name' => 'Escola Provisioned',
            'slug' => 'escola-provisioned',
            'email' => 'contato@provisioned.com',
            'admin_name' => 'Admin Provisioned',
            'admin_email' => 'admin@provisioned.com',
            'admin_password' => 'secret1234',
            'admin_password_confirmation' => 'secret1234',
        ], $overrides);
    }

    // -- super_admin can provision school + admin via POST /platform/schools --

    public function test_super_admin_can_provision_school_and_admin(): void
    {
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)
            ->post(route('platform.schools.store'), $this->validPayload());

        $response->assertRedirect(route('platform.schools.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('schools', [
            'name' => 'Escola Provisioned',
            'slug' => 'escola-provisioned',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@provisioned.com',
            'role' => 'school_admin',
        ]);

        // Verify admin belongs to the newly created school
        $school = School::where('slug', 'escola-provisioned')->first();
        $admin = User::where('email', 'admin@provisioned.com')->first();
        $this->assertSame($school->id, $admin->school_id);
    }

    // -- Validation fails without admin_name, admin_email, admin_password --

    public function test_validation_fails_without_admin_name(): void
    {
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)
            ->post(route('platform.schools.store'), $this->validPayload([
                'admin_name' => '',
            ]));

        $response->assertSessionHasErrors('admin_name');
    }

    public function test_validation_fails_without_admin_email(): void
    {
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)
            ->post(route('platform.schools.store'), $this->validPayload([
                'admin_email' => '',
            ]));

        $response->assertSessionHasErrors('admin_email');
    }

    public function test_validation_fails_without_admin_password(): void
    {
        $superAdmin = $this->superAdmin();

        $response = $this->actingAs($superAdmin)
            ->post(route('platform.schools.store'), $this->validPayload([
                'admin_password' => '',
            ]));

        $response->assertSessionHasErrors('admin_password');
    }

    public function test_validation_fails_without_all_required_admin_fields(): void
    {
        $superAdmin = $this->superAdmin();

        $payload = $this->validPayload();
        unset($payload['admin_name'], $payload['admin_email'], $payload['admin_password']);

        $response = $this->actingAs($superAdmin)
            ->post(route('platform.schools.store'), $payload);

        $response->assertSessionHasErrors(['admin_name', 'admin_email', 'admin_password']);
    }

    // -- school_admin cannot access the provisioning route (403) --

    public function test_school_admin_cannot_access_platform_provisioning_route(): void
    {
        $schoolAdmin = $this->schoolAdmin();

        $response = $this->actingAs($schoolAdmin)
            ->post(route('platform.schools.store'), $this->validPayload());

        $response->assertStatus(403);
    }

    // -- Duplicate email returns validation error --

    public function test_duplicate_admin_email_returns_validation_error(): void
    {
        $superAdmin = $this->superAdmin();

        User::factory()->create(['email' => 'duplicate@test.com']);

        $response = $this->actingAs($superAdmin)
            ->post(route('platform.schools.store'), $this->validPayload([
                'admin_email' => 'duplicate@test.com',
            ]));

        $response->assertSessionHasErrors('admin_email');
    }
}
