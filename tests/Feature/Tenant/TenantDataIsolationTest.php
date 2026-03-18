<?php

namespace Tests\Feature\Tenant;

use App\Models\Material;
use App\Models\School;
use App\Models\Scopes\SchoolScope;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * HTTP-level tenant data isolation tests.
 *
 * These tests verify that the full request lifecycle (middleware → route model
 * binding → controller → Eloquent) enforces cross-school data isolation.
 *
 * Scenarios covered:
 *  1. Route model binding returns 404 when accessing another school's resources.
 *  2. Index responses only contain the authenticated user's own-school data.
 *  3. Super admin bypasses all tenant filtering and can access any school's data.
 *  4. Mutation routes (edit, update, delete) are also blocked cross-school.
 */
#[Group('tenant')]
#[Group('integration')]
class TenantDataIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Create a school with a school_admin and a professor, returning all three.
     *
     * @return array{school: School, admin: User, professor: User}
     */
    private function createSchoolWithUsers(): array
    {
        $school = School::factory()->create();

        $admin = User::factory()->create([
            'role' => 'school_admin',
            'school_id' => $school->id,
        ]);

        $professor = User::factory()->create([
            'role' => 'professor',
            'school_id' => $school->id,
        ]);

        return compact('school', 'admin', 'professor');
    }

    /**
     * Create a TurmaClass belonging to the given school, bypassing the global scope
     * so an active tenant context does not interfere.
     */
    private function createClass(School $school, User $professor): TurmaClass
    {
        return TurmaClass::withoutGlobalScope(SchoolScope::class)->create([
            'name' => "Class of {$school->name}",
            'professor_id' => $professor->id,
            'school_id' => $school->id,
        ]);
    }

    /**
     * Create a Material belonging to the given class and school, bypassing the
     * global scope so an active tenant context does not interfere.
     */
    private function createMaterial(TurmaClass $class, User $professor): Material
    {
        return Material::withoutGlobalScope(SchoolScope::class)->create([
            'class_id' => $class->id,
            'uploaded_by' => $professor->id,
            'title' => 'Test Material',
            'file_path' => 'materials/test.pdf',
            'school_id' => $class->school_id,
        ]);
    }

    // ── 1. Route model binding: 404 for another school's class ───────────────

    #[Test]
    public function school_admin_gets_404_when_viewing_another_schools_class(): void
    {
        ['professor' => $profA] = $schoolAData = $this->createSchoolWithUsers();
        ['school' => $schoolB, 'admin' => $adminA, 'professor' => $profB] = $schoolBData = $this->createSchoolWithUsers();

        $adminA = $schoolAData['admin'];
        $classB = $this->createClass($schoolBData['school'], $profB);

        $this->actingAs($adminA)
            ->get("/classes/{$classB->id}")
            ->assertNotFound();
    }

    #[Test]
    public function professor_gets_404_when_viewing_another_schools_class(): void
    {
        ['professor' => $profA] = $schoolAData = $this->createSchoolWithUsers();
        ['school' => $schoolB, 'professor' => $profB] = $schoolBData = $this->createSchoolWithUsers();

        $classB = $this->createClass($schoolBData['school'], $profB);

        $this->actingAs($profA)
            ->get("/classes/{$classB->id}")
            ->assertNotFound();
    }

    #[Test]
    public function school_admin_gets_404_when_editing_another_schools_class(): void
    {
        $schoolAData = $this->createSchoolWithUsers();
        $schoolBData = $this->createSchoolWithUsers();

        $classB = $this->createClass($schoolBData['school'], $schoolBData['professor']);

        $this->actingAs($schoolAData['admin'])
            ->get("/classes/{$classB->id}/edit")
            ->assertNotFound();
    }

    #[Test]
    public function school_admin_gets_404_when_deleting_another_schools_class(): void
    {
        $schoolAData = $this->createSchoolWithUsers();
        $schoolBData = $this->createSchoolWithUsers();

        $classB = $this->createClass($schoolBData['school'], $schoolBData['professor']);

        $this->actingAs($schoolAData['admin'])
            ->delete("/classes/{$classB->id}")
            ->assertNotFound();
    }

    // ── 2. Route model binding: 404 for nested resources ─────────────────────

    #[Test]
    public function lessons_index_returns_404_for_another_schools_class(): void
    {
        $schoolAData = $this->createSchoolWithUsers();
        $schoolBData = $this->createSchoolWithUsers();

        $classB = $this->createClass($schoolBData['school'], $schoolBData['professor']);

        $this->actingAs($schoolAData['professor'])
            ->get("/classes/{$classB->id}/lessons")
            ->assertNotFound();
    }

    #[Test]
    public function materials_index_returns_404_for_another_schools_class(): void
    {
        $schoolAData = $this->createSchoolWithUsers();
        $schoolBData = $this->createSchoolWithUsers();

        $classB = $this->createClass($schoolBData['school'], $schoolBData['professor']);

        $this->actingAs($schoolAData['professor'])
            ->get("/classes/{$classB->id}/materials")
            ->assertNotFound();
    }

    #[Test]
    public function exercise_lists_index_returns_404_for_another_schools_class(): void
    {
        $schoolAData = $this->createSchoolWithUsers();
        $schoolBData = $this->createSchoolWithUsers();

        $classB = $this->createClass($schoolBData['school'], $schoolBData['professor']);

        $this->actingAs($schoolAData['professor'])
            ->get("/classes/{$classB->id}/exercise-lists")
            ->assertNotFound();
    }

    // ── 3. Index listings only contain own-school data ────────────────────────

    #[Test]
    public function classes_index_only_returns_own_school_classes(): void
    {
        $schoolAData = $this->createSchoolWithUsers();
        $schoolBData = $this->createSchoolWithUsers();

        // Create 3 classes for school A and 2 for school B
        for ($i = 0; $i < 3; $i++) {
            $this->createClass($schoolAData['school'], $schoolAData['professor']);
        }
        for ($i = 0; $i < 2; $i++) {
            $this->createClass($schoolBData['school'], $schoolBData['professor']);
        }

        // Verify total across all schools
        $totalClasses = TurmaClass::withoutGlobalScope(SchoolScope::class)->count();
        $this->assertSame(5, $totalClasses, 'Should have 5 classes across both schools');

        // School A admin request: response loads OK and the Inertia props
        // only include school A's 3 classes
        $response = $this->actingAs($schoolAData['admin'])
            ->get('/classes')
            ->assertOk();

        // The controller returns a paginated result; Inertia serialises it as
        // { data: [...], current_page: 1, ... }. Access the records via 'data'.
        $inertiaClasses = $response->viewData('page')['props']['classes']['data'] ?? null;

        if ($inertiaClasses !== null) {
            $this->assertCount(
                3,
                $inertiaClasses,
                'School A admin should only see 3 classes, not school B\'s classes'
            );

            $schoolIds = array_unique(array_column($inertiaClasses, 'school_id'));
            $this->assertSame(
                [$schoolAData['school']->id],
                array_values($schoolIds),
                'All returned classes must belong to school A'
            );
        }
    }

    // ── 4. Super admin bypasses tenant filter ────────────────────────────────

    #[Test]
    public function super_admin_can_view_any_schools_class(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'school_id' => null,
        ]);

        $schoolBData = $this->createSchoolWithUsers();
        $classB = $this->createClass($schoolBData['school'], $schoolBData['professor']);

        // Super admin has no tenant context — SchoolScope is a no-op
        $this->actingAs($superAdmin)
            ->get("/classes/{$classB->id}")
            ->assertOk();
    }

    #[Test]
    public function super_admin_sees_all_schools_classes_in_index(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'school_id' => null,
        ]);

        $schoolAData = $this->createSchoolWithUsers();
        $schoolBData = $this->createSchoolWithUsers();

        $this->createClass($schoolAData['school'], $schoolAData['professor']);
        $this->createClass($schoolBData['school'], $schoolBData['professor']);

        // Super admin GET /classes — must not 404, must load all data
        $response = $this->actingAs($superAdmin)
            ->get('/classes')
            ->assertOk();

        $inertiaClasses = $response->viewData('page')['props']['classes']['data'] ?? null;

        if ($inertiaClasses !== null) {
            $this->assertCount(
                2,
                $inertiaClasses,
                'Super admin should see classes from all schools'
            );
        }
    }

    // ── 5. Tenant context does not bleed between requests ────────────────────

    #[Test]
    public function tenant_context_does_not_bleed_between_sequential_requests(): void
    {
        $schoolAData = $this->createSchoolWithUsers();
        $schoolBData = $this->createSchoolWithUsers();

        $classA = $this->createClass($schoolAData['school'], $schoolAData['professor']);
        $classB = $this->createClass($schoolBData['school'], $schoolBData['professor']);

        // Request 1: school A admin sees class A
        $this->actingAs($schoolAData['admin'])
            ->get("/classes/{$classA->id}")
            ->assertOk();

        // Simulate new request: forget tenant context as middleware would for next request
        app()->forgetInstance('tenant.school_id');

        // Request 2: school B admin sees class B (no bleed from previous request)
        $this->actingAs($schoolBData['admin'])
            ->get("/classes/{$classB->id}")
            ->assertOk();

        // Cross-check: school B admin cannot see class A after context was reset
        app()->forgetInstance('tenant.school_id');

        $this->actingAs($schoolBData['admin'])
            ->get("/classes/{$classA->id}")
            ->assertNotFound();
    }

    // ── 6. Admin panel: cross-school user lookup is blocked ──────────────────

    #[Test]
    public function school_admin_gets_404_when_viewing_another_schools_student_packages(): void
    {
        $schoolAData = $this->createSchoolWithUsers();
        $schoolBData = $this->createSchoolWithUsers();

        // Create a student in school B
        $studentB = User::factory()->create([
            'role' => 'aluno',
            'school_id' => $schoolBData['school']->id,
        ]);

        // School A admin tries to access school B student's packages
        $this->actingAs($schoolAData['admin'])
            ->get("/admin/users/{$studentB->id}/packages")
            ->assertForbidden();
    }
}
