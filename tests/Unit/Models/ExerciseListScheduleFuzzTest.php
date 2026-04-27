<?php

namespace Tests\Unit\Models;

use App\Models\ExerciseList;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Scopes\SchoolScope;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fuzz tests for ExerciseList and Schedule BelongsToSchool edge cases.
 *
 * Probes: school_id=0 in tenant context, explicit school_id override,
 * cross-school invisibility, and null school_id visibility for super_admin.
 *
 * Covers TKL-002.
 */
class ExerciseListScheduleFuzzTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    /**
     * Helper: create a school with a professor and a class inside it.
     */
    private function createSchoolWithClass(?string $schoolName = null): array
    {
        $school = School::factory()->create($schoolName ? ['name' => $schoolName] : []);
        $professor = User::factory()->create([
            'role' => 'professor',
            'school_id' => $school->id,
        ]);
        $class = TurmaClass::withoutGlobalScope(SchoolScope::class)->create([
            'name' => 'Class '.$school->name,
            'professor_id' => $professor->id,
            'school_id' => $school->id,
        ]);

        return compact('school', 'professor', 'class');
    }

    // ── school_id = 0 in tenant context should NOT auto-assign ─────

    public function test_tenant_context_with_school_id_zero_causes_fk_violation_on_exercise_list(): void
    {
        $data = $this->createSchoolWithClass();

        // Bind tenant context with school_id = 0 (invalid).
        // BelongsToSchool creating event: model school_id is null,
        // tenant IS bound with value 0, so it sets school_id = 0.
        // However, SQLite enforces FK constraint: no school with id=0 exists,
        // so the insert fails. This is the correct defense-in-depth behavior.
        app()->instance('tenant.school_id', 0);

        $this->expectException(QueryException::class);

        ExerciseList::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'class_id' => $data['class']->id,
            'created_by' => $data['professor']->id,
            'title' => 'Zero Tenant Test',
        ]);
    }

    public function test_tenant_context_with_school_id_zero_causes_fk_violation_on_schedule(): void
    {
        $data = $this->createSchoolWithClass();

        // Same as above: tenant context 0 triggers FK violation on insert.
        app()->instance('tenant.school_id', 0);

        $this->expectException(QueryException::class);

        Schedule::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'class_id' => $data['class']->id,
            'weekday' => 1,
            'start_time' => '10:00',
            'duration_minutes' => 60,
            'active' => true,
        ]);
    }

    // ── Explicit school_id on model is NOT overridden by tenant ────

    public function test_exercise_list_with_explicit_school_id_not_overridden_by_different_tenant(): void
    {
        $dataA = $this->createSchoolWithClass('School A');
        $dataB = $this->createSchoolWithClass('School B');

        // Bind tenant A, but explicitly set school_id to B.
        app()->instance('tenant.school_id', $dataA['school']->id);

        $list = ExerciseList::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'class_id' => $dataB['class']->id,
            'created_by' => $dataB['professor']->id,
            'title' => 'Explicit B in Tenant A',
            'school_id' => $dataB['school']->id,
        ]);

        // BelongsToSchool creating event: model school_id is NOT null and NOT 0,
        // so the auto-assign is skipped. Explicit value wins.
        $this->assertSame($dataB['school']->id, $list->school_id);
        $this->assertSame($dataB['school']->id, $list->fresh()->school_id);
        $this->assertNotSame($dataA['school']->id, $list->school_id);
    }

    // ── Cross-school invisibility: ExerciseList ────────────────────

    public function test_exercise_list_from_school_a_is_invisible_to_school_b_tenant(): void
    {
        $dataA = $this->createSchoolWithClass('School A');
        $dataB = $this->createSchoolWithClass('School B');

        // Create lists in each school.
        ExerciseList::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'class_id' => $dataA['class']->id,
            'created_by' => $dataA['professor']->id,
            'title' => 'List from A',
            'school_id' => $dataA['school']->id,
        ]);
        ExerciseList::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'class_id' => $dataB['class']->id,
            'created_by' => $dataB['professor']->id,
            'title' => 'List from B',
            'school_id' => $dataB['school']->id,
        ]);

        // Bind tenant B.
        app()->instance('tenant.school_id', $dataB['school']->id);

        $visibleLists = ExerciseList::all();

        $this->assertCount(1, $visibleLists);
        $this->assertSame('List from B', $visibleLists->first()->title);

        // School A's list is invisible.
        $titles = $visibleLists->pluck('title')->toArray();
        $this->assertNotContains('List from A', $titles);
    }

    // ── Cross-school invisibility: Schedule ────────────────────────

    public function test_schedule_from_school_a_is_invisible_to_school_b_tenant(): void
    {
        $dataA = $this->createSchoolWithClass('School A');
        $dataB = $this->createSchoolWithClass('School B');

        Schedule::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'class_id' => $dataA['class']->id,
            'weekday' => 1,
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'active' => true,
            'school_id' => $dataA['school']->id,
        ]);
        Schedule::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'class_id' => $dataB['class']->id,
            'weekday' => 3,
            'start_time' => '14:00',
            'duration_minutes' => 45,
            'active' => true,
            'school_id' => $dataB['school']->id,
        ]);

        // Bind tenant A.
        app()->instance('tenant.school_id', $dataA['school']->id);

        $visibleSchedules = Schedule::all();

        $this->assertCount(1, $visibleSchedules);
        $this->assertSame(1, $visibleSchedules->first()->weekday); // Monday = school A

        // School B's schedule (weekday 3) is invisible.
        $weekdays = $visibleSchedules->pluck('weekday')->toArray();
        $this->assertNotContains(3, $weekdays);
    }

    // ── Null school_id visible when no tenant context (super_admin) ─

    public function test_exercise_list_with_null_school_id_visible_without_tenant_context(): void
    {
        $data = $this->createSchoolWithClass();

        // Create an ExerciseList with null school_id (no tenant, no explicit).
        $orphanList = ExerciseList::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'class_id' => $data['class']->id,
            'created_by' => $data['professor']->id,
            'title' => 'Orphan (null school_id)',
        ]);

        // Also create one with a real school_id.
        ExerciseList::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'class_id' => $data['class']->id,
            'created_by' => $data['professor']->id,
            'title' => 'Owned by School',
            'school_id' => $data['school']->id,
        ]);

        // No tenant context (super_admin view): all items visible.
        $allLists = ExerciseList::withoutGlobalScope(SchoolScope::class)->get();

        $this->assertCount(2, $allLists);
        $this->assertNull($orphanList->fresh()->school_id);

        // The orphan list IS visible.
        $titles = $allLists->pluck('title')->toArray();
        $this->assertContains('Orphan (null school_id)', $titles);
    }

    // ── Null school_id INVISIBLE when tenant context IS bound ──────

    public function test_exercise_list_with_null_school_id_invisible_when_tenant_bound(): void
    {
        $data = $this->createSchoolWithClass();

        // Orphan list (null school_id).
        ExerciseList::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'class_id' => $data['class']->id,
            'created_by' => $data['professor']->id,
            'title' => 'Orphan List',
        ]);

        // Owned list.
        ExerciseList::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'class_id' => $data['class']->id,
            'created_by' => $data['professor']->id,
            'title' => 'Owned List',
            'school_id' => $data['school']->id,
        ]);

        // Bind tenant.
        app()->instance('tenant.school_id', $data['school']->id);

        $visibleLists = ExerciseList::all();

        // SchoolScope adds WHERE school_id = X, so null school_id rows are excluded.
        $this->assertCount(1, $visibleLists);
        $this->assertSame('Owned List', $visibleLists->first()->title);
    }

    // ── Model with school_id = 0 in DB is NOT matched by any real tenant ─

    public function test_exercise_list_with_school_id_zero_rejected_by_fk_constraint(): void
    {
        $data = $this->createSchoolWithClass();

        // SQLite FK constraint prevents inserting school_id = 0 (no school with id=0).
        // This is the database-level defense against invalid tenant references.
        $this->expectException(QueryException::class);

        ExerciseList::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'class_id' => $data['class']->id,
            'created_by' => $data['professor']->id,
            'title' => 'Zero School ID',
            'school_id' => 0,
        ]);
    }

    // ── Schedule with null school_id: same visibility rules ────────

    public function test_schedule_with_null_school_id_invisible_when_tenant_bound(): void
    {
        $data = $this->createSchoolWithClass();

        // Orphan schedule (null school_id).
        Schedule::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'class_id' => $data['class']->id,
            'weekday' => 5,
            'start_time' => '16:00',
            'duration_minutes' => 60,
            'active' => true,
        ]);

        // Owned schedule.
        Schedule::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'class_id' => $data['class']->id,
            'weekday' => 2,
            'start_time' => '08:00',
            'duration_minutes' => 60,
            'active' => true,
            'school_id' => $data['school']->id,
        ]);

        app()->instance('tenant.school_id', $data['school']->id);

        $visibleSchedules = Schedule::all();

        $this->assertCount(1, $visibleSchedules);
        $this->assertSame(2, $visibleSchedules->first()->weekday);
    }
}
