<?php

namespace Tests\Feature\Schedules;

use App\Models\LessonPackage;
use App\Models\Schedule;
use App\Models\ScheduledLesson;
use App\Models\School;
use App\Models\Scopes\SchoolScope;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifies the schedule-aware dashboard projections:
 *   - Aluno: next_lesson points to the soonest enrolled scheduled slot.
 *   - Professor: week_schedule lists slots within the next 7 days.
 */
#[Group('schedules')]
#[Group('dashboard')]
class DashboardScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    private function makeClassWithStudent(School $school, User $professor): array
    {
        $class = TurmaClass::withoutGlobalScope(SchoolScope::class)->create([
            'name' => 'Turma Dash',
            'professor_id' => $professor->id,
            'school_id' => $school->id,
        ]);

        $student = User::factory()->create(['role' => 'aluno', 'school_id' => $school->id]);
        $class->students()->attach($student->id);

        LessonPackage::withoutGlobalScope(SchoolScope::class)->create([
            'student_id' => $student->id,
            'school_id' => $school->id,
            'total_lessons' => 10,
            'currency' => 'BRL',
            'purchased_at' => now(),
        ]);

        return [$class, $student];
    }

    private function makeSlot(TurmaClass $class, School $school, \DateTimeInterface $when, int $duration = 60): ScheduledLesson
    {
        $schedule = Schedule::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'class_id' => $class->id,
            'school_id' => $school->id,
            'weekday' => (int) $when->format('w'),
            'start_time' => $when->format('H:i'),
            'duration_minutes' => $duration,
            'active' => true,
        ]);

        return ScheduledLesson::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'schedule_id' => $schedule->id,
            'class_id' => $class->id,
            'school_id' => $school->id,
            'scheduled_at' => $when,
            'status' => 'scheduled',
        ]);
    }

    #[Test]
    public function aluno_dashboard_includes_next_scheduled_lesson(): void
    {
        $school = School::factory()->create();
        $professor = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);
        [$class, $student] = $this->makeClassWithStudent($school, $professor);

        $when = now()->addDays(2)->setTime(19, 0);
        $this->makeSlot($class, $school, $when);

        $response = $this->actingAs($student)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Dashboard')
                ->where('stats.next_lesson.class_name', 'Turma Dash')
                ->where('stats.next_lesson.professor', $professor->name)
                ->where('stats.next_lesson.duration_minutes', 60)
        );
    }

    #[Test]
    public function aluno_dashboard_returns_null_next_lesson_when_no_schedule(): void
    {
        $school = School::factory()->create();
        $professor = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);
        [$class, $student] = $this->makeClassWithStudent($school, $professor);
        // Intentionally no slots created.
        unset($class);

        $response = $this->actingAs($student)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Dashboard')
                ->where('stats.next_lesson', null)
        );
    }

    #[Test]
    public function professor_dashboard_includes_week_schedule_within_seven_days(): void
    {
        $school = School::factory()->create();
        $professor = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);
        [$class] = $this->makeClassWithStudent($school, $professor);

        // Within window
        $this->makeSlot($class, $school, now()->addDays(1)->setTime(10, 0));
        $this->makeSlot($class, $school, now()->addDays(3)->setTime(11, 0));
        // Out of window (>7 days) — should NOT appear
        $this->makeSlot($class, $school, now()->addDays(20)->setTime(10, 0));

        $response = $this->actingAs($professor)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Dashboard')
                ->has('stats.week_schedule', 2)
        );
    }
}
