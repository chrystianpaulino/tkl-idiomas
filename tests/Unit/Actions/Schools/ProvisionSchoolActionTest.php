<?php

namespace Tests\Unit\Actions\Schools;

use App\Actions\Schools\ProvisionSchoolAction;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProvisionSchoolActionTest extends TestCase
{
    use RefreshDatabase;

    private ProvisionSchoolAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ProvisionSchoolAction;
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Escola Teste',
            'slug' => 'escola-teste',
            'email' => 'contato@escolateste.com',
            'admin_name' => 'Admin Teste',
            'admin_email' => 'admin@escolateste.com',
            'admin_password' => 'secret123',
        ], $overrides);
    }

    // ── School creation ─────────────────────────────────────────

    public function test_creates_school_record(): void
    {
        $countBefore = School::count();

        $this->action->execute($this->validData());

        $this->assertSame($countBefore + 1, School::count());
    }

    public function test_school_is_active(): void
    {
        $result = $this->action->execute($this->validData());

        $this->assertTrue($result['school']->active);
    }

    public function test_school_has_correct_slug(): void
    {
        $result = $this->action->execute($this->validData(['slug' => 'my-school']));

        $this->assertSame('my-school', $result['school']->slug);
    }

    public function test_school_email_is_persisted(): void
    {
        $result = $this->action->execute($this->validData(['email' => 'info@test.com']));

        $this->assertSame('info@test.com', $result['school']->email);
    }

    // ── Admin creation ──────────────────────────────────────────

    public function test_creates_school_admin_user(): void
    {
        $this->action->execute($this->validData());

        $this->assertSame(1, User::where('role', 'school_admin')->count());
    }

    public function test_admin_belongs_to_provisioned_school(): void
    {
        $result = $this->action->execute($this->validData());

        $this->assertSame($result['school']->id, $result['admin']->school_id);
    }

    public function test_password_is_hashed(): void
    {
        $result = $this->action->execute($this->validData(['admin_password' => 'my-secret']));

        $this->assertTrue(Hash::check('my-secret', $result['admin']->password));
    }

    public function test_admin_role_is_school_admin(): void
    {
        $result = $this->action->execute($this->validData());

        $this->assertSame('school_admin', $result['admin']->role);
    }

    // ── Return structure ────────────────────────────────────────

    public function test_returns_array_with_school_and_admin_keys(): void
    {
        $result = $this->action->execute($this->validData());

        $this->assertArrayHasKey('school', $result);
        $this->assertArrayHasKey('admin', $result);
        $this->assertInstanceOf(School::class, $result['school']);
        $this->assertInstanceOf(User::class, $result['admin']);
    }

    // ── Transaction rollback ────────────────────────────────────

    public function test_transaction_rolls_back_on_duplicate_admin_email(): void
    {
        // Pre-create a user with the same email
        User::factory()->create(['email' => 'duplicate@test.com']);

        try {
            $this->action->execute($this->validData([
                'admin_email' => 'duplicate@test.com',
            ]));
        } catch (\Throwable) {
            // Expected: unique constraint violation
        }

        // School should have been rolled back (only the pre-existing user's school remains)
        $this->assertSame(0, School::where('slug', 'escola-teste')->count());
    }
}
