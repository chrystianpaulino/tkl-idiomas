<?php

namespace Tests\Feature\Authorization;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifies UserPolicy authorization rules across all four roles.
 *
 * Coverage matrix:
 *   - super_admin → bypassed by Gate::before (always allowed)
 *   - school_admin → same-school only, cannot edit other admins
 *   - professor → only self-view / self-edit
 *   - aluno → only self-view / self-edit
 *
 * Self-access is universally permitted (a user can always view/update
 * themselves). Self-deletion is universally denied (no admin lock-out).
 */
class UserPolicyTest extends TestCase
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
    public function super_admin_can_view_any_users_list(): void
    {
        $this->actingAs($this->makeUser('super_admin'));
        $this->assertTrue(Gate::allows('viewAny', User::class));
    }

    #[Test]
    public function school_admin_can_view_any_users_list(): void
    {
        $this->actingAs($this->makeUser('school_admin'));
        $this->assertTrue(Gate::allows('viewAny', User::class));
    }

    #[Test]
    public function professor_cannot_view_users_list(): void
    {
        $this->actingAs($this->makeUser('professor'));
        $this->assertFalse(Gate::allows('viewAny', User::class));
    }

    #[Test]
    public function aluno_cannot_view_users_list(): void
    {
        $this->actingAs($this->makeUser('aluno'));
        $this->assertFalse(Gate::allows('viewAny', User::class));
    }

    // ── view (individual) ─────────────────────────────────────────────────

    #[Test]
    public function any_user_can_view_themselves(): void
    {
        foreach (['super_admin', 'school_admin', 'professor', 'aluno'] as $role) {
            $user = $this->makeUser($role);
            $this->actingAs($user);
            $this->assertTrue(Gate::allows('view', $user), "Role {$role} should view self");
        }
    }

    #[Test]
    public function school_admin_can_view_users_in_same_school(): void
    {
        $admin = $this->makeUser('school_admin', $this->schoolA);
        $student = $this->makeUser('aluno', $this->schoolA);

        $this->actingAs($admin);
        $this->assertTrue(Gate::allows('view', $student));
    }

    #[Test]
    public function school_admin_cannot_view_users_from_another_school(): void
    {
        $admin = $this->makeUser('school_admin', $this->schoolA);
        $foreigner = $this->makeUser('aluno', $this->schoolB);

        $this->actingAs($admin);
        $this->assertFalse(Gate::allows('view', $foreigner));
    }

    #[Test]
    public function professor_cannot_view_other_users(): void
    {
        $professor = $this->makeUser('professor', $this->schoolA);
        $student = $this->makeUser('aluno', $this->schoolA);

        $this->actingAs($professor);
        $this->assertFalse(Gate::allows('view', $student));
    }

    // ── create ────────────────────────────────────────────────────────────

    #[Test]
    public function super_admin_can_create_users(): void
    {
        $this->actingAs($this->makeUser('super_admin'));
        $this->assertTrue(Gate::allows('create', User::class));
    }

    #[Test]
    public function school_admin_can_create_users(): void
    {
        $this->actingAs($this->makeUser('school_admin'));
        $this->assertTrue(Gate::allows('create', User::class));
    }

    #[Test]
    public function professor_cannot_create_users(): void
    {
        $this->actingAs($this->makeUser('professor'));
        $this->assertFalse(Gate::allows('create', User::class));
    }

    #[Test]
    public function aluno_cannot_create_users(): void
    {
        $this->actingAs($this->makeUser('aluno'));
        $this->assertFalse(Gate::allows('create', User::class));
    }

    // ── update ────────────────────────────────────────────────────────────

    #[Test]
    public function school_admin_can_update_aluno_in_same_school(): void
    {
        $admin = $this->makeUser('school_admin', $this->schoolA);
        $aluno = $this->makeUser('aluno', $this->schoolA);

        $this->actingAs($admin);
        $this->assertTrue(Gate::allows('update', $aluno));
    }

    #[Test]
    public function school_admin_can_update_professor_in_same_school(): void
    {
        $admin = $this->makeUser('school_admin', $this->schoolA);
        $professor = $this->makeUser('professor', $this->schoolA);

        $this->actingAs($admin);
        $this->assertTrue(Gate::allows('update', $professor));
    }

    #[Test]
    public function school_admin_cannot_update_another_school_admin(): void
    {
        $admin1 = $this->makeUser('school_admin', $this->schoolA);
        $admin2 = $this->makeUser('school_admin', $this->schoolA);

        $this->actingAs($admin1);
        $this->assertFalse(Gate::allows('update', $admin2));
    }

    #[Test]
    public function school_admin_cannot_update_super_admin(): void
    {
        $admin = $this->makeUser('school_admin', $this->schoolA);
        $superAdmin = $this->makeUser('super_admin');

        $this->actingAs($admin);
        $this->assertFalse(Gate::allows('update', $superAdmin));
    }

    #[Test]
    public function school_admin_cannot_update_user_from_another_school(): void
    {
        $admin = $this->makeUser('school_admin', $this->schoolA);
        $foreignAluno = $this->makeUser('aluno', $this->schoolB);

        $this->actingAs($admin);
        $this->assertFalse(Gate::allows('update', $foreignAluno));
    }

    #[Test]
    public function aluno_cannot_update_another_user(): void
    {
        $aluno1 = $this->makeUser('aluno', $this->schoolA);
        $aluno2 = $this->makeUser('aluno', $this->schoolA);

        $this->actingAs($aluno1);
        $this->assertFalse(Gate::allows('update', $aluno2));
    }

    // ── delete ────────────────────────────────────────────────────────────

    #[Test]
    public function school_admin_can_delete_aluno_in_same_school(): void
    {
        $admin = $this->makeUser('school_admin', $this->schoolA);
        $aluno = $this->makeUser('aluno', $this->schoolA);

        $this->actingAs($admin);
        $this->assertTrue(Gate::allows('delete', $aluno));
    }

    #[Test]
    public function school_admin_cannot_delete_themselves(): void
    {
        $admin = $this->makeUser('school_admin', $this->schoolA);

        $this->actingAs($admin);
        $this->assertFalse(Gate::allows('delete', $admin));
    }

    #[Test]
    public function school_admin_cannot_delete_another_school_admin(): void
    {
        $admin1 = $this->makeUser('school_admin', $this->schoolA);
        $admin2 = $this->makeUser('school_admin', $this->schoolA);

        $this->actingAs($admin1);
        $this->assertFalse(Gate::allows('delete', $admin2));
    }

    #[Test]
    public function school_admin_cannot_delete_user_from_another_school(): void
    {
        $admin = $this->makeUser('school_admin', $this->schoolA);
        $foreignAluno = $this->makeUser('aluno', $this->schoolB);

        $this->actingAs($admin);
        $this->assertFalse(Gate::allows('delete', $foreignAluno));
    }

    #[Test]
    public function professor_cannot_delete_anyone(): void
    {
        $professor = $this->makeUser('professor', $this->schoolA);
        $aluno = $this->makeUser('aluno', $this->schoolA);

        $this->actingAs($professor);
        $this->assertFalse(Gate::allows('delete', $aluno));
    }

    // ── assignRole ───────────────────────────────────────────────────────

    #[Test]
    public function super_admin_can_assign_any_role(): void
    {
        $this->actingAs($this->makeUser('super_admin'));

        foreach (['super_admin', 'school_admin', 'professor', 'aluno'] as $role) {
            $this->assertTrue(
                Gate::allows('assignRole', [User::class, $role]),
                "super_admin should be able to assign {$role}"
            );
        }
    }

    #[Test]
    public function school_admin_can_only_assign_professor_or_aluno(): void
    {
        $this->actingAs($this->makeUser('school_admin', $this->schoolA));

        $this->assertTrue(Gate::allows('assignRole', [User::class, 'professor']));
        $this->assertTrue(Gate::allows('assignRole', [User::class, 'aluno']));
        $this->assertFalse(Gate::allows('assignRole', [User::class, 'school_admin']));
        $this->assertFalse(Gate::allows('assignRole', [User::class, 'super_admin']));
    }

    #[Test]
    public function professor_cannot_assign_any_role(): void
    {
        $this->actingAs($this->makeUser('professor', $this->schoolA));

        foreach (['super_admin', 'school_admin', 'professor', 'aluno'] as $role) {
            $this->assertFalse(Gate::allows('assignRole', [User::class, $role]));
        }
    }
}
