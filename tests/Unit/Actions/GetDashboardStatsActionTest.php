<?php

namespace Tests\Unit\Actions;

use App\Actions\GetDashboardStatsAction;
use App\Models\LessonPackage;
use App\Models\School;
use App\Models\TurmaClass;
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

    // ── professorStats ─────────────────────────────────────────────

    public function test_professor_stats_returns_classes_array_with_correct_structure(): void
    {
        $school = School::factory()->create();

        $professor = User::factory()->professor()->create(['school_id' => $school->id]);

        $class1 = TurmaClass::factory()->create([
            'professor_id' => $professor->id,
            'school_id' => $school->id,
            'name' => 'English A1',
        ]);
        $class2 = TurmaClass::factory()->create([
            'professor_id' => $professor->id,
            'school_id' => $school->id,
            'name' => 'English B2',
        ]);

        app()->instance('tenant.school_id', $school->id);

        $stats = $this->action->execute($professor);

        $this->assertArrayHasKey('classes', $stats);
        $this->assertCount(2, $stats['classes']);
        $this->assertArrayHasKey('total_classes', $stats);
        $this->assertSame(2, $stats['total_classes']);
        $this->assertArrayHasKey('total_lessons_taught', $stats);
        $this->assertArrayHasKey('recent_lessons', $stats);
        $this->assertArrayHasKey('studentsNeedingPackage', $stats);
        $this->assertArrayHasKey('class_payment_stats', $stats);

        // Verify class structure
        $classItem = $stats['classes']->first();
        $this->assertArrayHasKey('id', $classItem);
        $this->assertArrayHasKey('name', $classItem);
    }

    public function test_professor_stats_returns_empty_classes_when_no_classes_assigned(): void
    {
        $school = School::factory()->create();
        $professor = User::factory()->professor()->create(['school_id' => $school->id]);

        app()->instance('tenant.school_id', $school->id);

        $stats = $this->action->execute($professor);

        $this->assertCount(0, $stats['classes']);
        $this->assertSame(0, $stats['total_classes']);
    }

    // ── alunoStats ─────────────────────────────────────────────────

    public function test_aluno_stats_remaining_equals_sum_across_all_active_packages(): void
    {
        $school = School::factory()->create();
        $student = User::factory()->create(['school_id' => $school->id]);

        // Package 1: 10 total, 3 used = 7 remaining
        $pkg1 = LessonPackage::factory()->create([
            'student_id' => $student->id,
            'total_lessons' => 10,
            'school_id' => $school->id,
            'expires_at' => null,
        ]);
        $pkg1->used_lessons = 3;
        $pkg1->save();

        // Package 2: 5 total, 1 used = 4 remaining
        $pkg2 = LessonPackage::factory()->create([
            'student_id' => $student->id,
            'total_lessons' => 5,
            'school_id' => $school->id,
            'expires_at' => now()->addMonth(),
        ]);
        $pkg2->used_lessons = 1;
        $pkg2->save();

        app()->instance('tenant.school_id', $school->id);

        $stats = $this->action->execute($student);

        // 7 + 4 = 11 remaining across both active packages
        $this->assertSame(11, $stats['stats']['remaining']);
    }

    public function test_aluno_stats_low_credits_true_when_remaining_lte_2(): void
    {
        $school = School::factory()->create();
        $student = User::factory()->create(['school_id' => $school->id]);

        $pkg = LessonPackage::factory()->create([
            'student_id' => $student->id,
            'total_lessons' => 5,
            'school_id' => $school->id,
            'expires_at' => null,
        ]);
        $pkg->used_lessons = 3;
        $pkg->save();
        // remaining = 2

        app()->instance('tenant.school_id', $school->id);

        $stats = $this->action->execute($student);

        $this->assertSame(2, $stats['stats']['remaining']);
        $this->assertTrue($stats['stats']['low_credits']);
    }

    public function test_aluno_stats_low_credits_false_when_remaining_gt_2(): void
    {
        $school = School::factory()->create();
        $student = User::factory()->create(['school_id' => $school->id]);

        $pkg = LessonPackage::factory()->create([
            'student_id' => $student->id,
            'total_lessons' => 10,
            'school_id' => $school->id,
            'expires_at' => null,
        ]);
        $pkg->used_lessons = 2;
        $pkg->save();
        // remaining = 8

        app()->instance('tenant.school_id', $school->id);

        $stats = $this->action->execute($student);

        $this->assertSame(8, $stats['stats']['remaining']);
        $this->assertFalse($stats['stats']['low_credits']);
    }

    public function test_aluno_stats_includes_payment_history(): void
    {
        $school = School::factory()->create();
        $student = User::factory()->create(['school_id' => $school->id]);

        LessonPackage::factory()->create([
            'student_id' => $student->id,
            'total_lessons' => 10,
            'school_id' => $school->id,
            'expires_at' => null,
        ]);

        app()->instance('tenant.school_id', $school->id);

        $stats = $this->action->execute($student);

        $this->assertArrayHasKey('payment_history', $stats);
    }

    public function test_aluno_stats_has_expected_keys(): void
    {
        $school = School::factory()->create();
        $student = User::factory()->create(['school_id' => $school->id]);

        LessonPackage::factory()->create([
            'student_id' => $student->id,
            'total_lessons' => 10,
            'school_id' => $school->id,
            'expires_at' => null,
        ]);

        app()->instance('tenant.school_id', $school->id);

        $stats = $this->action->execute($student);

        $this->assertArrayHasKey('activePackage', $stats);
        $this->assertArrayHasKey('warning', $stats);
        $this->assertArrayHasKey('recentLessons', $stats);
        $this->assertArrayHasKey('enrolledClasses', $stats);
        $this->assertArrayHasKey('stats', $stats);
        $this->assertArrayHasKey('progress', $stats);
        $this->assertArrayHasKey('payment_history', $stats);
    }

    public function test_aluno_stats_warning_exhausted_when_no_active_package_but_had_one(): void
    {
        $school = School::factory()->create();
        $student = User::factory()->create(['school_id' => $school->id]);

        // Create an exhausted package so student "had" one
        LessonPackage::factory()->exhausted()->create([
            'student_id' => $student->id,
            'total_lessons' => 5,
            'school_id' => $school->id,
        ]);

        app()->instance('tenant.school_id', $school->id);

        $stats = $this->action->execute($student);

        $this->assertNull($stats['activePackage']);
        $this->assertEquals('exhausted', $stats['warning']);
    }

    public function test_aluno_stats_warning_no_package_when_never_had_one(): void
    {
        $school = School::factory()->create();
        $student = User::factory()->create(['school_id' => $school->id]);

        app()->instance('tenant.school_id', $school->id);

        $stats = $this->action->execute($student);

        $this->assertNull($stats['activePackage']);
        $this->assertEquals('no_package', $stats['warning']);
    }
}
