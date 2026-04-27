<?php

namespace Tests\Feature\Authorization;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifies SchoolPolicy authorization rules across all four roles.
 *
 * Coverage matrix:
 *   - super_admin → bypassed by Gate::before (always allowed)
 *   - school_admin → view/update OWN school only; cannot create or delete
 *   - professor / aluno → no access to school management at all
 *
 * Crucially, school_admin CANNOT delete the school they administer -- that
 * would cascade-delete every tenant record (see School::booted) and is
 * reserved for super_admin acting deliberately.
 */
class SchoolPolicyTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;

    private School $schoolB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::factory()->create();
        $this->schoolB = School::factory()->create();
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    private function makeUser(string $role, ?School $school = null): User
    {
        $user = User::factory()->create([
            'school_id' => $role === 'super_admin' ? null : ($school?->id ?? $this->schoolA->id),
        ]);
        $user->role = $role;
        $user->save();

        return $user;
    }

    // ── viewAny ───────────────────────────────────────────────────────────

    #[Test]
    public function super_admin_can_view_schools_list(): void
    {
        $this->actingAs($this->makeUser('super_admin'));
        $this->assertTrue(Gate::allows('viewAny', School::class));
    }

    #[Test]
    public function school_admin_can_view_schools_list(): void
    {
        $this->actingAs($this->makeUser('school_admin'));
        $this->assertTrue(Gate::allows('viewAny', School::class));
    }

    #[Test]
    public function professor_cannot_view_schools_list(): void
    {
        $this->actingAs($this->makeUser('professor'));
        $this->assertFalse(Gate::allows('viewAny', School::class));
    }

    #[Test]
    public function aluno_cannot_view_schools_list(): void
    {
        $this->actingAs($this->makeUser('aluno'));
        $this->assertFalse(Gate::allows('viewAny', School::class));
    }

    // ── view (individual) ─────────────────────────────────────────────────

    #[Test]
    public function super_admin_can_view_any_school(): void
    {
        $this->actingAs($this->makeUser('super_admin'));
        $this->assertTrue(Gate::allows('view', $this->schoolA));
        $this->assertTrue(Gate::allows('view', $this->schoolB));
    }

    #[Test]
    public function school_admin_can_view_own_school(): void
    {
        $this->actingAs($this->makeUser('school_admin', $this->schoolA));
        $this->assertTrue(Gate::allows('view', $this->schoolA));
    }

    #[Test]
    public function school_admin_cannot_view_other_school(): void
    {
        $this->actingAs($this->makeUser('school_admin', $this->schoolA));
        $this->assertFalse(Gate::allows('view', $this->schoolB));
    }

    #[Test]
    public function professor_cannot_view_any_school(): void
    {
        $this->actingAs($this->makeUser('professor', $this->schoolA));
        $this->assertFalse(Gate::allows('view', $this->schoolA));
        $this->assertFalse(Gate::allows('view', $this->schoolB));
    }

    // ── create ────────────────────────────────────────────────────────────

    #[Test]
    public function super_admin_can_create_school(): void
    {
        $this->actingAs($this->makeUser('super_admin'));
        $this->assertTrue(Gate::allows('create', School::class));
    }

    #[Test]
    public function school_admin_cannot_create_school(): void
    {
        $this->actingAs($this->makeUser('school_admin'));
        $this->assertFalse(Gate::allows('create', School::class));
    }

    #[Test]
    public function professor_cannot_create_school(): void
    {
        $this->actingAs($this->makeUser('professor'));
        $this->assertFalse(Gate::allows('create', School::class));
    }

    #[Test]
    public function aluno_cannot_create_school(): void
    {
        $this->actingAs($this->makeUser('aluno'));
        $this->assertFalse(Gate::allows('create', School::class));
    }

    // ── update ────────────────────────────────────────────────────────────

    #[Test]
    public function super_admin_can_update_any_school(): void
    {
        $this->actingAs($this->makeUser('super_admin'));
        $this->assertTrue(Gate::allows('update', $this->schoolA));
        $this->assertTrue(Gate::allows('update', $this->schoolB));
    }

    #[Test]
    public function school_admin_can_update_own_school(): void
    {
        $this->actingAs($this->makeUser('school_admin', $this->schoolA));
        $this->assertTrue(Gate::allows('update', $this->schoolA));
    }

    #[Test]
    public function school_admin_cannot_update_other_school(): void
    {
        $this->actingAs($this->makeUser('school_admin', $this->schoolA));
        $this->assertFalse(Gate::allows('update', $this->schoolB));
    }

    #[Test]
    public function professor_cannot_update_any_school(): void
    {
        $this->actingAs($this->makeUser('professor', $this->schoolA));
        $this->assertFalse(Gate::allows('update', $this->schoolA));
    }

    // ── delete ────────────────────────────────────────────────────────────

    #[Test]
    public function super_admin_can_delete_any_school(): void
    {
        $this->actingAs($this->makeUser('super_admin'));
        $this->assertTrue(Gate::allows('delete', $this->schoolA));
        $this->assertTrue(Gate::allows('delete', $this->schoolB));
    }

    #[Test]
    public function school_admin_cannot_delete_their_own_school(): void
    {
        // Critical safety rule: a school administrator must NEVER be able to
        // erase the school they administer (catastrophic cascading data loss).
        $this->actingAs($this->makeUser('school_admin', $this->schoolA));
        $this->assertFalse(Gate::allows('delete', $this->schoolA));
    }

    #[Test]
    public function school_admin_cannot_delete_other_school(): void
    {
        $this->actingAs($this->makeUser('school_admin', $this->schoolA));
        $this->assertFalse(Gate::allows('delete', $this->schoolB));
    }

    #[Test]
    public function professor_cannot_delete_any_school(): void
    {
        $this->actingAs($this->makeUser('professor', $this->schoolA));
        $this->assertFalse(Gate::allows('delete', $this->schoolA));
    }
}
