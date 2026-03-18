<?php

namespace Tests\Unit\Models;

use App\Actions\Schools\ProvisionSchoolAction;
use App\Models\School;
use App\Models\Scopes\SchoolScope;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TenantIsolationInvariantTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    // ── Helpers ─────────────────────────────────────────────────────

    /**
     * Bind the tenant context to a given school.
     */
    private function bindTenant(School $school): void
    {
        app()->instance('tenant.school_id', $school->id);
    }

    /**
     * Create N TurmaClass records for a school, bypassing the global scope
     * so the creating event does not interfere with explicit school_id.
     */
    private function createClassesForSchool(School $school, int $count, User $professor): void
    {
        for ($i = 0; $i < $count; $i++) {
            TurmaClass::withoutGlobalScope(SchoolScope::class)->create([
                'name' => "Class {$i} of {$school->name}",
                'professor_id' => $professor->id,
                'school_id' => $school->id,
            ]);
        }
    }

    // ── Invariant 1: Perfect isolation across N schools ─────────────

    public function test_query_isolation_holds_across_multiple_schools(): void
    {
        $classesPerSchool = 3;
        $schoolCount = 5;
        $schools = [];
        $professors = [];

        // Arrange: create 5 schools, each with a professor and 3 classes
        for ($i = 0; $i < $schoolCount; $i++) {
            $school = School::factory()->create(['name' => "School {$i}"]);
            $professor = User::factory()->professor()->create(['school_id' => $school->id]);

            $this->createClassesForSchool($school, $classesPerSchool, $professor);

            $schools[] = $school;
            $professors[] = $professor;
        }

        // Verify total without tenant context (no-op scope)
        $this->assertSame(
            $schoolCount * $classesPerSchool,
            TurmaClass::withoutGlobalScope(SchoolScope::class)->count(),
            'Total classes across all schools should be '.($schoolCount * $classesPerSchool)
        );

        // Act & Assert: for each school, bind tenant and verify isolation
        foreach ($schools as $school) {
            app()->forgetInstance('tenant.school_id');
            $this->bindTenant($school);

            $this->assertSame(
                $classesPerSchool,
                TurmaClass::count(),
                "Tenant bound to '{$school->name}' should see exactly {$classesPerSchool} classes"
            );

            $visibleSchoolIds = TurmaClass::pluck('school_id')->unique()->values()->all();
            $this->assertSame(
                [$school->id],
                $visibleSchoolIds,
                "All visible classes must belong to school '{$school->name}' (id={$school->id})"
            );
        }
    }

    // ── Invariant 2: Provisioning always produces school_admin ownership ──

    public static function provisionDataProvider(): array
    {
        return [
            'english school' => [['name' => 'English Academy', 'slug' => 'english-academy', 'email' => 'info@english.com', 'admin_name' => 'Jane Doe', 'admin_email' => 'jane@english.com', 'admin_password' => 'secret123']],
            'math school' => [['name' => 'Math Pro', 'slug' => 'math-pro', 'email' => 'info@math.com', 'admin_name' => 'John Smith', 'admin_email' => 'john@math.com', 'admin_password' => 'pass456']],
            'music school' => [['name' => 'Music House', 'slug' => 'music-house', 'email' => 'info@music.com', 'admin_name' => 'Ana Lima', 'admin_email' => 'ana@music.com', 'admin_password' => 'mypass789']],
            'coding school' => [['name' => 'Code Camp', 'slug' => 'code-camp', 'email' => 'hi@code.com', 'admin_name' => 'Carlos Dev', 'admin_email' => 'carlos@code.com', 'admin_password' => 'devpass']],
            'art school' => [['name' => 'Art Studio', 'slug' => 'art-studio', 'email' => 'hello@art.com', 'admin_name' => 'Maria Arts', 'admin_email' => 'maria@art.com', 'admin_password' => 'artpass']],
        ];
    }

    #[DataProvider('provisionDataProvider')]
    public function test_provision_invariants_hold_for_all_inputs(array $data): void
    {
        // Act
        $result = (new ProvisionSchoolAction)->execute($data);

        $school = $result['school'];
        $admin = $result['admin'];

        // Assert: admin belongs to the created school
        $this->assertSame(
            $school->id,
            $admin->school_id,
            "Admin's school_id must equal the provisioned school's id"
        );

        // Assert: admin has school_admin role
        $this->assertSame(
            'school_admin',
            $admin->role,
            'Provisioned admin must have the school_admin role'
        );

        // Assert: school is active
        $this->assertTrue(
            $school->active,
            'Provisioned school must be active'
        );

        // Assert: admin record persisted in database
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'email' => $data['admin_email'],
            'role' => 'school_admin',
            'school_id' => $school->id,
        ]);

        // Assert: school record persisted in database
        $this->assertDatabaseHas('schools', [
            'id' => $school->id,
            'slug' => $data['slug'],
            'active' => true,
        ]);
    }

    // ── Invariant 3: Tenant isolation survives query builder chaining ──

    public function test_scope_survives_query_builder_chaining(): void
    {
        // Arrange: 2 schools, 5 classes each
        $schoolA = School::factory()->create(['name' => 'School A']);
        $schoolB = School::factory()->create(['name' => 'School B']);

        $profA = User::factory()->professor()->create(['school_id' => $schoolA->id]);
        $profB = User::factory()->professor()->create(['school_id' => $schoolB->id]);

        $this->createClassesForSchool($schoolA, 5, $profA);
        $this->createClassesForSchool($schoolB, 5, $profB);

        // Bind tenant to school A
        $this->bindTenant($schoolA);

        // Chaining: where() does not defeat the scope
        $countWithWhere = TurmaClass::where('id', '>', 0)->count();
        $this->assertSame(5, $countWithWhere, 'where() chaining must not bypass tenant scope');

        // Chaining: orderBy() does not defeat the scope
        $firstDesc = TurmaClass::orderBy('id', 'desc')->first();
        $this->assertNotNull($firstDesc);
        $this->assertSame(
            $schoolA->id,
            $firstDesc->school_id,
            'orderBy() chaining must not bypass tenant scope'
        );

        // Chaining: limit() does not defeat the scope
        $allWithLimit = TurmaClass::limit(100)->get();
        $this->assertCount(5, $allWithLimit, 'limit(100) must return only tenant classes');
        foreach ($allWithLimit as $class) {
            $this->assertSame(
                $schoolA->id,
                $class->school_id,
                'Every record from limit() query must belong to tenant school'
            );
        }

        // Chaining: latest() does not defeat the scope
        $latestRecord = TurmaClass::latest('id')->first();
        $this->assertNotNull($latestRecord);
        $this->assertSame(
            $schoolA->id,
            $latestRecord->school_id,
            'latest() chaining must not bypass tenant scope'
        );
    }

    // ── Invariant 4: Role helper consistency ────────────────────────

    public static function roleExclusivityProvider(): array
    {
        return [
            'super_admin' => ['super_admin',  ['isSuperAdmin' => true,  'isSchoolAdmin' => false, 'isProfessor' => false, 'isAluno' => false]],
            'school_admin' => ['school_admin', ['isSuperAdmin' => false, 'isSchoolAdmin' => true,  'isProfessor' => false, 'isAluno' => false]],
            'admin' => ['admin',        ['isSuperAdmin' => false, 'isSchoolAdmin' => false, 'isProfessor' => false, 'isAluno' => false]],
            'professor' => ['professor',    ['isSuperAdmin' => false, 'isSchoolAdmin' => false, 'isProfessor' => true,  'isAluno' => false]],
            'aluno' => ['aluno',        ['isSuperAdmin' => false, 'isSchoolAdmin' => false, 'isProfessor' => false, 'isAluno' => true]],
        ];
    }

    #[DataProvider('roleExclusivityProvider')]
    public function test_role_helpers_are_mutually_consistent(string $role, array $expected): void
    {
        $user = User::factory()->create(['role' => $role]);

        // Assert each helper returns the expected boolean
        $this->assertSame(
            $expected['isSuperAdmin'],
            $user->isSuperAdmin(),
            'isSuperAdmin() should be '.($expected['isSuperAdmin'] ? 'true' : 'false')." for role '{$role}'"
        );

        $this->assertSame(
            $expected['isSchoolAdmin'],
            $user->isSchoolAdmin(),
            'isSchoolAdmin() should be '.($expected['isSchoolAdmin'] ? 'true' : 'false')." for role '{$role}'"
        );

        $this->assertSame(
            $expected['isProfessor'],
            $user->isProfessor(),
            'isProfessor() should be '.($expected['isProfessor'] ? 'true' : 'false')." for role '{$role}'"
        );

        $this->assertSame(
            $expected['isAluno'],
            $user->isAluno(),
            'isAluno() should be '.($expected['isAluno'] ? 'true' : 'false')." for role '{$role}'"
        );

        // Invariant: exactly one of the four specific helpers returns true,
        // EXCEPT for the legacy 'admin' role where none of the four returns true
        $trueCount = array_sum([
            (int) $user->isSuperAdmin(),
            (int) $user->isSchoolAdmin(),
            (int) $user->isProfessor(),
            (int) $user->isAluno(),
        ]);

        if ($role === 'admin') {
            // Legacy admin role: none of the four specific helpers returns true
            $this->assertSame(0, $trueCount, "Legacy 'admin' role: none of the four specific helpers should return true");
            // But isAdmin() convenience helper returns true
            $this->assertTrue($user->isAdmin(), "isAdmin() must return true for legacy 'admin' role");
        } else {
            $this->assertSame(1, $trueCount, "Exactly one specific role helper should return true for role '{$role}'");
        }

        // isAdmin() is a convenience alias: true for 'admin' and 'school_admin' only
        $expectIsAdmin = in_array($role, ['admin', 'school_admin'], true);
        $this->assertSame(
            $expectIsAdmin,
            $user->isAdmin(),
            'isAdmin() should be '.($expectIsAdmin ? 'true' : 'false')." for role '{$role}'"
        );
    }

    // ── Invariant 5: BelongsToSchool auto-assignment never creates orphans ──

    public function test_auto_assignment_never_creates_orphaned_records(): void
    {
        $school = School::factory()->create();
        $professor = User::factory()->professor()->create(['school_id' => $school->id]);

        // Bind tenant context
        $this->bindTenant($school);

        // Create 10 classes without explicitly setting school_id
        $recordCount = 10;
        for ($i = 0; $i < $recordCount; $i++) {
            TurmaClass::create([
                'name' => "Auto Class {$i}",
                'professor_id' => $professor->id,
                // school_id intentionally omitted
            ]);
        }

        // Assert all 10 were created
        $classes = TurmaClass::all();
        $this->assertCount($recordCount, $classes, "All {$recordCount} classes must be created");

        // Assert every record has the tenant's school_id
        foreach ($classes as $class) {
            $this->assertSame(
                $school->id,
                $class->school_id,
                "Class '{$class->name}' must have school_id = {$school->id}"
            );
        }

        // Assert zero records have null school_id (check at DB level, bypassing scope)
        $orphanCount = TurmaClass::withoutGlobalScope(SchoolScope::class)
            ->whereNull('school_id')
            ->count();

        $this->assertSame(
            0,
            $orphanCount,
            'No records should have null school_id when tenant context is active'
        );
    }
}
