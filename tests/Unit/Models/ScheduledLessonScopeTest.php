<?php

namespace Tests\Unit\Models;

use App\Models\Schedule;
use App\Models\ScheduledLesson;
use App\Models\School;
use App\Models\Scopes\SchoolScope;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests BelongsToSchool multi-tenant behaviour on ScheduledLesson.
 *
 * Covers: ScheduledLesson scoped by school_id via BelongsToSchool trait.
 */
class ScheduledLessonScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    /**
     * Helper: create a school with a professor, class, and schedule inside it.
     */
    private function createSchoolWithClassAndSchedule(): array
    {
        $school = School::factory()->create();
        $professor = User::factory()->create([
            'role' => 'professor',
            'school_id' => $school->id,
        ]);
        // forceCreate bypasses the mass-assignment guard on tenant/ownership
        // foreign keys (school_id, class_id, professor_id) which production
        // code intentionally requires Action classes to set explicitly.
        $class = TurmaClass::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'name' => 'Class '.$school->name,
            'professor_id' => $professor->id,
            'school_id' => $school->id,
        ]);
        $schedule = Schedule::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'class_id' => $class->id,
            'weekday' => 1,
            'start_time' => '14:00',
            'duration_minutes' => 60,
            'active' => true,
            'school_id' => $school->id,
        ]);

        return compact('school', 'professor', 'class', 'schedule');
    }

    // -- Auto-assign school_id in tenant context --

    public function test_scheduled_lesson_created_in_tenant_context_gets_school_id_auto_assigned(): void
    {
        $data = $this->createSchoolWithClassAndSchedule();

        app()->instance('tenant.school_id', $data['school']->id);

        // forceFill applies foreign keys outside $fillable; the BelongsToSchool
        // creating event still auto-assigns school_id from tenant context.
        $scheduledLesson = new ScheduledLesson;
        $scheduledLesson->forceFill([
            'schedule_id' => $data['schedule']->id,
            'class_id' => $data['class']->id,
            'scheduled_at' => now()->addDays(1),
            'status' => 'scheduled',
        ]);
        $scheduledLesson->save();

        $this->assertSame($data['school']->id, $scheduledLesson->school_id);
    }

    // -- Scoped query filters by school_id --

    public function test_scheduled_lesson_query_in_tenant_context_is_scoped(): void
    {
        $dataA = $this->createSchoolWithClassAndSchedule();
        $dataB = $this->createSchoolWithClassAndSchedule();

        ScheduledLesson::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'schedule_id' => $dataA['schedule']->id,
            'class_id' => $dataA['class']->id,
            'scheduled_at' => now()->addDays(1),
            'status' => 'scheduled',
            'school_id' => $dataA['school']->id,
        ]);
        ScheduledLesson::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'schedule_id' => $dataB['schedule']->id,
            'class_id' => $dataB['class']->id,
            'scheduled_at' => now()->addDays(2),
            'status' => 'scheduled',
            'school_id' => $dataB['school']->id,
        ]);

        // Bind tenant A
        app()->instance('tenant.school_id', $dataA['school']->id);

        $lessons = ScheduledLesson::all();
        $this->assertCount(1, $lessons);
        $this->assertSame($dataA['school']->id, $lessons->first()->school_id);
    }

    // -- Other school's records not visible --

    public function test_scheduled_lesson_from_other_school_is_not_visible(): void
    {
        $dataA = $this->createSchoolWithClassAndSchedule();
        $dataB = $this->createSchoolWithClassAndSchedule();

        ScheduledLesson::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'schedule_id' => $dataB['schedule']->id,
            'class_id' => $dataB['class']->id,
            'scheduled_at' => now()->addDays(1),
            'status' => 'scheduled',
            'school_id' => $dataB['school']->id,
        ]);

        // Bind tenant A — school B's record should not be visible
        app()->instance('tenant.school_id', $dataA['school']->id);

        $this->assertCount(0, ScheduledLesson::all());
    }

    // -- Super admin sees all (no tenant context) --

    public function test_super_admin_sees_all_scheduled_lessons_without_tenant_context(): void
    {
        $dataA = $this->createSchoolWithClassAndSchedule();
        $dataB = $this->createSchoolWithClassAndSchedule();

        ScheduledLesson::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'schedule_id' => $dataA['schedule']->id,
            'class_id' => $dataA['class']->id,
            'scheduled_at' => now()->addDays(1),
            'status' => 'scheduled',
            'school_id' => $dataA['school']->id,
        ]);
        ScheduledLesson::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'schedule_id' => $dataB['schedule']->id,
            'class_id' => $dataB['class']->id,
            'scheduled_at' => now()->addDays(2),
            'status' => 'scheduled',
            'school_id' => $dataB['school']->id,
        ]);

        // No tenant binding — super_admin view
        $lessons = ScheduledLesson::all();
        $this->assertCount(2, $lessons);
    }

    // -- Edge cases --

    public function test_scheduled_lesson_explicit_school_id_not_overridden_by_tenant(): void
    {
        $dataA = $this->createSchoolWithClassAndSchedule();
        $dataB = $this->createSchoolWithClassAndSchedule();

        // Bind tenant A, but explicitly set school B
        app()->instance('tenant.school_id', $dataA['school']->id);

        $lesson = ScheduledLesson::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'schedule_id' => $dataB['schedule']->id,
            'class_id' => $dataB['class']->id,
            'scheduled_at' => now()->addDays(1),
            'status' => 'scheduled',
            'school_id' => $dataB['school']->id,
        ]);

        $this->assertSame($dataB['school']->id, $lesson->fresh()->school_id);
    }

    public function test_scheduled_lesson_created_without_tenant_and_without_school_id_has_null(): void
    {
        $data = $this->createSchoolWithClassAndSchedule();

        // No tenant context, no explicit school_id — should remain null
        $lesson = ScheduledLesson::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'schedule_id' => $data['schedule']->id,
            'class_id' => $data['class']->id,
            'scheduled_at' => now()->addDays(1),
            'status' => 'scheduled',
        ]);

        $this->assertNull($lesson->school_id);
    }
}
