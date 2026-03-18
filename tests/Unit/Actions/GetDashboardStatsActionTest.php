<?php

namespace Tests\Unit\Actions;

use App\Actions\GetDashboardStatsAction;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests adminStats() tenant scoping in GetDashboardStatsAction.
 *
 * Covers TKL-003: adminStats() scoping.
 */
class GetDashboardStatsActionTest extends TestCase
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
     * Create a school with a school_admin, N students, and N professors.
     */
    private function createSchoolWithUsers(int $students = 0, int $professors = 0): array
    {
        $school = School::factory()->create();

        $admin = new User;
        $admin->name = 'Admin '.$school->name;
        $admin->email = 'admin-'.$school->id.'@test.com';
        $admin->password = bcrypt('password');
        $admin->role = 'school_admin';
        $admin->school_id = $school->id;
        $admin->email_verified_at = now();
        $admin->save();

        $studentList = [];
        for ($i = 0; $i < $students; $i++) {
            $student = User::factory()->create([
                'role' => 'aluno',
                'school_id' => $school->id,
            ]);
            $studentList[] = $student;
        }

        $professorList = [];
        for ($i = 0; $i < $professors; $i++) {
            $professor = User::factory()->create([
                'role' => 'professor',
                'school_id' => $school->id,
            ]);
            $professorList[] = $professor;
        }

        return compact('school', 'admin', 'studentList', 'professorList');
    }

    // ── school_admin sees only own school's students ─────────────

    public function test_admin_stats_returns_only_own_school_student_count(): void
    {
        $schoolA = $this->createSchoolWithUsers(students: 3, professors: 1);
        $schoolB = $this->createSchoolWithUsers(students: 5, professors: 2);

        $stats = $this->action->execute($schoolA['admin']);

        $this->assertSame(3, $stats['total_students']);
    }

    // ── school_admin sees only own school's professors ───────────

    public function test_admin_stats_returns_only_own_school_professor_count(): void
    {
        $schoolA = $this->createSchoolWithUsers(students: 2, professors: 2);
        $schoolB = $this->createSchoolWithUsers(students: 1, professors: 4);

        $stats = $this->action->execute($schoolA['admin']);

        $this->assertSame(2, $stats['total_professors']);
    }

    // ── super_admin (null school_id) sees all schools' counts ────

    public function test_super_admin_stats_returns_all_schools_counts(): void
    {
        $schoolA = $this->createSchoolWithUsers(students: 3, professors: 1);
        $schoolB = $this->createSchoolWithUsers(students: 5, professors: 2);

        $superAdmin = new User;
        $superAdmin->name = 'Super Admin';
        $superAdmin->email = 'super@test.com';
        $superAdmin->password = bcrypt('password');
        $superAdmin->role = 'super_admin';
        $superAdmin->school_id = null;
        $superAdmin->email_verified_at = now();
        $superAdmin->save();

        // super_admin uses superAdminStats, which counts all students
        $stats = $this->action->execute($superAdmin);

        // 3 + 5 = 8 students across both schools
        $this->assertSame(8, $stats['total_students']);
    }

    // ── Cross-school isolation: school_admin A cannot see B ──────

    public function test_cross_school_isolation_admin_a_cannot_see_school_b_users(): void
    {
        $schoolA = $this->createSchoolWithUsers(students: 2, professors: 1);
        $schoolB = $this->createSchoolWithUsers(students: 7, professors: 3);

        $statsA = $this->action->execute($schoolA['admin']);
        $statsB = $this->action->execute($schoolB['admin']);

        // A sees only A's users
        $this->assertSame(2, $statsA['total_students']);
        $this->assertSame(1, $statsA['total_professors']);

        // B sees only B's users
        $this->assertSame(7, $statsB['total_students']);
        $this->assertSame(3, $statsB['total_professors']);

        // Verify they don't bleed
        $this->assertNotSame($statsA['total_students'], $statsB['total_students']);
    }

    // ── Edge cases ───────────────────────────────────────────────

    public function test_admin_stats_returns_zero_when_school_has_no_students(): void
    {
        $school = $this->createSchoolWithUsers(students: 0, professors: 0);

        $stats = $this->action->execute($school['admin']);

        $this->assertSame(0, $stats['total_students']);
        $this->assertSame(0, $stats['total_professors']);
    }

    public function test_admin_stats_includes_expected_keys(): void
    {
        $school = $this->createSchoolWithUsers(students: 1, professors: 1);

        $stats = $this->action->execute($school['admin']);

        $this->assertArrayHasKey('total_students', $stats);
        $this->assertArrayHasKey('total_professors', $stats);
        $this->assertArrayHasKey('total_classes', $stats);
        $this->assertArrayHasKey('total_lessons', $stats);
        $this->assertArrayHasKey('active_packages', $stats);
        $this->assertArrayHasKey('payment_summary', $stats);
    }

    public function test_admin_stats_with_legacy_admin_role_also_scoped(): void
    {
        $schoolA = $this->createSchoolWithUsers(students: 4, professors: 2);
        $schoolB = $this->createSchoolWithUsers(students: 6, professors: 3);

        // Create a user with legacy 'admin' role
        $legacyAdmin = new User;
        $legacyAdmin->name = 'Legacy Admin';
        $legacyAdmin->email = 'legacy@test.com';
        $legacyAdmin->password = bcrypt('password');
        $legacyAdmin->role = 'admin';
        $legacyAdmin->school_id = $schoolA['school']->id;
        $legacyAdmin->email_verified_at = now();
        $legacyAdmin->save();

        $stats = $this->action->execute($legacyAdmin);

        // Legacy admin with school_id should see only school A's users
        $this->assertSame(4, $stats['total_students']);
        $this->assertSame(2, $stats['total_professors']);
    }

    public function test_super_admin_stats_has_correct_shape(): void
    {
        $this->createSchoolWithUsers(students: 1, professors: 1);

        $superAdmin = new User;
        $superAdmin->name = 'Super';
        $superAdmin->email = 'super2@test.com';
        $superAdmin->password = bcrypt('password');
        $superAdmin->role = 'super_admin';
        $superAdmin->school_id = null;
        $superAdmin->email_verified_at = now();
        $superAdmin->save();

        $stats = $this->action->execute($superAdmin);

        $this->assertArrayHasKey('total_schools', $stats);
        $this->assertArrayHasKey('total_students', $stats);
        $this->assertArrayHasKey('total_revenue', $stats);
        $this->assertArrayHasKey('active_schools', $stats);
    }
}
