<?php

namespace Tests\Unit\Models;

use App\Models\ExerciseList;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Scopes\SchoolScope;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests BelongsToSchool multi-tenant behaviour on ExerciseList and Schedule.
 *
 * Covers TKL-002: BelongsToSchool on ExerciseList and Schedule.
 */
class ExerciseListScheduleScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    /**
     * Helper: create a school with a professor and a class inside it (bypassing scope).
     */
    private function createSchoolWithClass(): array
    {
        $school = School::factory()->create();
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

    // ── ExerciseList: auto-assign school_id in tenant context ────

    public function test_exercise_list_created_in_tenant_context_gets_school_id_auto_assigned(): void
    {
        $data = $this->createSchoolWithClass();

        app()->instance('tenant.school_id', $data['school']->id);

        // class_id and created_by are outside ExerciseList::$fillable; we use
        // forceCreate so the test exercises the BelongsToSchool creating
        // event (which auto-fills school_id from the bound tenant).
        $exerciseList = ExerciseList::forceCreate([
            'class_id' => $data['class']->id,
            'created_by' => $data['professor']->id,
            'title' => 'Homework 1',
        ]);

        $this->assertSame($data['school']->id, $exerciseList->school_id);
    }

    // ── ExerciseList: scoped query in tenant context ─────────────

    public function test_exercise_list_query_in_tenant_context_is_scoped(): void
    {
        $dataA = $this->createSchoolWithClass();
        $dataB = $this->createSchoolWithClass();

        // Create exercise lists bypassing scope
        ExerciseList::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'class_id' => $dataA['class']->id,
            'created_by' => $dataA['professor']->id,
            'title' => 'List A',
            'school_id' => $dataA['school']->id,
        ]);
        ExerciseList::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'class_id' => $dataB['class']->id,
            'created_by' => $dataB['professor']->id,
            'title' => 'List B',
            'school_id' => $dataB['school']->id,
        ]);

        // Bind tenant A
        app()->instance('tenant.school_id', $dataA['school']->id);

        $lists = ExerciseList::all();
        $this->assertCount(1, $lists);
        $this->assertSame('List A', $lists->first()->title);
    }

    // ── ExerciseList: no tenant context (super_admin) returns all ─

    public function test_exercise_list_query_without_tenant_context_returns_all(): void
    {
        $dataA = $this->createSchoolWithClass();
        $dataB = $this->createSchoolWithClass();

        ExerciseList::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'class_id' => $dataA['class']->id,
            'created_by' => $dataA['professor']->id,
            'title' => 'List A',
            'school_id' => $dataA['school']->id,
        ]);
        ExerciseList::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'class_id' => $dataB['class']->id,
            'created_by' => $dataB['professor']->id,
            'title' => 'List B',
            'school_id' => $dataB['school']->id,
        ]);

        // No tenant binding — super_admin view
        $lists = ExerciseList::all();
        $this->assertCount(2, $lists);
    }

    // ── Schedule: auto-assign school_id in tenant context ────────

    public function test_schedule_created_in_tenant_context_gets_school_id_auto_assigned(): void
    {
        $data = $this->createSchoolWithClass();

        app()->instance('tenant.school_id', $data['school']->id);

        // class_id is outside Schedule::$fillable; forceCreate exercises the
        // BelongsToSchool creating event which still auto-fills school_id
        // from the bound tenant.
        $schedule = Schedule::forceCreate([
            'class_id' => $data['class']->id,
            'weekday' => 1,
            'start_time' => '14:00',
            'duration_minutes' => 60,
            'active' => true,
        ]);

        $this->assertSame($data['school']->id, $schedule->school_id);
    }

    // ── Schedule: scoped query in tenant context ─────────────────

    public function test_schedule_query_in_tenant_context_is_scoped(): void
    {
        $dataA = $this->createSchoolWithClass();
        $dataB = $this->createSchoolWithClass();

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
            'start_time' => '10:00',
            'duration_minutes' => 45,
            'active' => true,
            'school_id' => $dataB['school']->id,
        ]);

        // Bind tenant A
        app()->instance('tenant.school_id', $dataA['school']->id);

        $schedules = Schedule::all();
        $this->assertCount(1, $schedules);
        $this->assertSame(1, $schedules->first()->weekday);
    }

    // ── Schedule: no tenant context returns all ──────────────────

    public function test_schedule_query_without_tenant_context_returns_all(): void
    {
        $dataA = $this->createSchoolWithClass();
        $dataB = $this->createSchoolWithClass();

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
            'start_time' => '10:00',
            'duration_minutes' => 45,
            'active' => true,
            'school_id' => $dataB['school']->id,
        ]);

        // No tenant binding
        $schedules = Schedule::all();
        $this->assertCount(2, $schedules);
    }

    // ── Edge cases ───────────────────────────────────────────────

    public function test_exercise_list_explicit_school_id_not_overridden_by_tenant(): void
    {
        $dataA = $this->createSchoolWithClass();
        $dataB = $this->createSchoolWithClass();

        // Bind tenant A, but explicitly set school B
        app()->instance('tenant.school_id', $dataA['school']->id);

        $list = ExerciseList::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'class_id' => $dataB['class']->id,
            'created_by' => $dataB['professor']->id,
            'title' => 'Explicit School B',
            'school_id' => $dataB['school']->id,
        ]);

        $this->assertSame($dataB['school']->id, $list->fresh()->school_id);
    }

    public function test_schedule_explicit_school_id_not_overridden_by_tenant(): void
    {
        $dataA = $this->createSchoolWithClass();
        $dataB = $this->createSchoolWithClass();

        app()->instance('tenant.school_id', $dataA['school']->id);

        $schedule = Schedule::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'class_id' => $dataB['class']->id,
            'weekday' => 5,
            'start_time' => '16:00',
            'duration_minutes' => 90,
            'active' => true,
            'school_id' => $dataB['school']->id,
        ]);

        $this->assertSame($dataB['school']->id, $schedule->fresh()->school_id);
    }

    public function test_exercise_list_created_without_tenant_and_without_school_id_has_null(): void
    {
        $data = $this->createSchoolWithClass();

        // No tenant context, no explicit school_id — should remain null
        $list = ExerciseList::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'class_id' => $data['class']->id,
            'created_by' => $data['professor']->id,
            'title' => 'Orphan List',
        ]);

        $this->assertNull($list->school_id);
    }

    public function test_schedule_created_without_tenant_and_without_school_id_has_null(): void
    {
        $data = $this->createSchoolWithClass();

        $schedule = Schedule::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'class_id' => $data['class']->id,
            'weekday' => 2,
            'start_time' => '08:00',
            'duration_minutes' => 60,
            'active' => true,
        ]);

        $this->assertNull($schedule->school_id);
    }
}
