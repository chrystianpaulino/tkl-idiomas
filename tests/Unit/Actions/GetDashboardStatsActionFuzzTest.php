<?php

namespace Tests\Unit\Actions;

use App\Actions\GetDashboardStatsAction;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fuzz tests for GetDashboardStatsAction::adminStats() edge cases.
 *
 * Probes: school_id=0 handling, cross-school stat isolation,
 * zero-student school, and unknown role handling.
 *
 * Covers TKL-003.
 */
class GetDashboardStatsActionFuzzTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    private GetDashboardStatsAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new GetDashboardStatsAction;
    }

    /**
     * Create a user with the given role, bypassing $fillable guards on role/school_id.
     */
    private function createUser(string $role, ?int $schoolId, array $extraAttrs = []): User
    {
        $user = new User;
        $user->name = $extraAttrs['name'] ?? 'User '.uniqid();
        $user->email = $extraAttrs['email'] ?? uniqid().'@test.com';
        $user->password = bcrypt('password');
        $user->role = $role;
        $user->school_id = $schoolId;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    /**
     * Create a school with a school_admin and optional students/professors.
     */
    private function createSchoolWithUsers(int $students = 0, int $professors = 0): array
    {
        $school = School::factory()->create();
        $admin = $this->createUser('school_admin', $school->id);

        $studentList = [];
        for ($i = 0; $i < $students; $i++) {
            $studentList[] = $this->createUser('aluno', $school->id);
        }

        $professorList = [];
        for ($i = 0; $i < $professors; $i++) {
            $professorList[] = $this->createUser('professor', $school->id);
        }

        return compact('school', 'admin', 'studentList', 'professorList');
    }

    // ── school_admin with school_id = 0: should not crash ──────────

    public function test_school_admin_with_school_id_zero_returns_empty_counts(): void
    {
        // Create a school_admin with school_id = 0 (invalid but possible in corrupt data).
        // adminStats() uses: ->when($schoolId !== null, fn ($q) => $q->where('school_id', $schoolId))
        // With school_id = 0: $schoolId !== null is true, so it filters WHERE school_id = 0.
        // No users have school_id = 0, so counts should be 0.
        $user = new User;
        $user->name = 'Admin Zero';
        $user->email = 'admin-zero@test.com';
        $user->password = bcrypt('password');
        $user->role = 'school_admin';
        $user->school_id = null; // Create with null first (FK constraint).
        $user->email_verified_at = now();
        $user->save();

        // Set school_id = 0 in-memory only to bypass FK constraint.
        $user->setAttribute('school_id', 0);

        // Create real data in another school to ensure no bleed.
        $this->createSchoolWithUsers(students: 5, professors: 2);

        $stats = $this->action->execute($user);

        // Should not crash. school_id = 0 filters to no results.
        $this->assertIsArray($stats);
        $this->assertSame(0, $stats['total_students']);
        $this->assertSame(0, $stats['total_professors']);
        $this->assertSame(0, $stats['total_classes']);
        $this->assertSame(0, $stats['total_lessons']);
        $this->assertSame(0, $stats['active_packages']);
    }

    // ── Two school_admins: stats do not bleed ──────────────────────

    public function test_two_school_admins_stats_are_completely_isolated(): void
    {
        $schoolA = $this->createSchoolWithUsers(students: 3, professors: 1);
        $schoolB = $this->createSchoolWithUsers(students: 7, professors: 4);

        $statsA = $this->action->execute($schoolA['admin']);
        $statsB = $this->action->execute($schoolB['admin']);

        // School A sees exactly its own counts.
        $this->assertSame(3, $statsA['total_students']);
        $this->assertSame(1, $statsA['total_professors']);

        // School B sees exactly its own counts.
        $this->assertSame(7, $statsB['total_students']);
        $this->assertSame(4, $statsB['total_professors']);

        // Cross-check: A does not see B's data and vice versa.
        $this->assertNotSame($statsA['total_students'], $statsB['total_students']);
        $this->assertNotSame($statsA['total_professors'], $statsB['total_professors']);

        // Sum verification: total across both = 10 students, 5 professors.
        $this->assertSame(10, $statsA['total_students'] + $statsB['total_students']);
        $this->assertSame(5, $statsA['total_professors'] + $statsB['total_professors']);
    }

    // ── school_admin with zero students returns 0, not null ────────

    public function test_admin_stats_with_no_students_returns_zero_not_null(): void
    {
        $school = $this->createSchoolWithUsers(students: 0, professors: 0);

        $stats = $this->action->execute($school['admin']);

        $this->assertSame(0, $stats['total_students']);
        $this->assertIsInt($stats['total_students']);
        $this->assertNotNull($stats['total_students']);

        $this->assertSame(0, $stats['total_professors']);
        $this->assertIsInt($stats['total_professors']);

        $this->assertSame(0, $stats['total_classes']);
        $this->assertSame(0, $stats['total_lessons']);
        $this->assertSame(0, $stats['active_packages']);

        // payment_summary should also be zero/0.
        $this->assertArrayHasKey('payment_summary', $stats);
        $this->assertSame(0.0, $stats['payment_summary']['total_revenue']);
        $this->assertSame(0, $stats['payment_summary']['unpaid_count']);
    }

    // ── Unknown role returns empty array ───────────────────────────

    public function test_unknown_role_returns_empty_array(): void
    {
        $user = new User;
        $user->name = 'Unknown Role';
        $user->email = 'unknown-role@test.com';
        $user->password = bcrypt('password');
        $user->role = 'nonexistent_role';
        $user->school_id = null;
        $user->email_verified_at = now();
        $user->save();

        $stats = $this->action->execute($user);

        $this->assertIsArray($stats);
        $this->assertEmpty($stats);
    }

    // ── adminStats() called sequentially for different admins: no state bleed ─

    public function test_sequential_calls_for_different_admins_do_not_bleed(): void
    {
        $schoolA = $this->createSchoolWithUsers(students: 2, professors: 1);
        $schoolB = $this->createSchoolWithUsers(students: 8, professors: 3);

        // Call for admin A.
        $statsA = $this->action->execute($schoolA['admin']);

        // Call for admin B immediately after (same action instance).
        $statsB = $this->action->execute($schoolB['admin']);

        // Back to A again to verify no stale data.
        $statsA2 = $this->action->execute($schoolA['admin']);

        $this->assertSame(2, $statsA['total_students']);
        $this->assertSame(8, $statsB['total_students']);
        $this->assertSame(2, $statsA2['total_students']);
        $this->assertSame($statsA, $statsA2, 'Sequential calls for same admin must return identical results.');
    }

    // ── payment_summary types are correct ──────────────────────────

    public function test_payment_summary_types_are_correct_for_empty_school(): void
    {
        $school = $this->createSchoolWithUsers(students: 0, professors: 0);

        $stats = $this->action->execute($school['admin']);

        $summary = $stats['payment_summary'];

        $this->assertIsFloat($summary['total_revenue']);
        $this->assertIsInt($summary['unpaid_count']);
    }
}
