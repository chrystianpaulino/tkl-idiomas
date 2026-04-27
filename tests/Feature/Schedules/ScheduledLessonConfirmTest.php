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
 * End-to-end tests for the ScheduledLesson confirm flow.
 *
 * Verifies that confirmation creates Lesson records and consumes package credits
 * for every enrolled student, and that authorization is enforced on both the
 * professor (must own the parent class) and student (forbidden) sides.
 */
#[Group('schedules')]
class ScheduledLessonConfirmTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    private function setupClassWithStudent(School $school, User $professor): array
    {
        $class = TurmaClass::withoutGlobalScope(SchoolScope::class)->create([
            'name' => 'Turma X',
            'professor_id' => $professor->id,
            'school_id' => $school->id,
        ]);

        $student = User::factory()->create(['role' => 'aluno', 'school_id' => $school->id]);
        $class->students()->attach($student->id);

        $package = LessonPackage::withoutGlobalScope(SchoolScope::class)->create([
            'student_id' => $student->id,
            'school_id' => $school->id,
            'total_lessons' => 10,
            'currency' => 'BRL',
            'purchased_at' => now(),
        ]);

        $schedule = Schedule::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'class_id' => $class->id,
            'school_id' => $school->id,
            'weekday' => 1,
            'start_time' => '19:00',
            'duration_minutes' => 60,
            'active' => true,
        ]);

        $scheduledLesson = ScheduledLesson::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'schedule_id' => $schedule->id,
            'class_id' => $class->id,
            'school_id' => $school->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);

        return compact('class', 'student', 'package', 'schedule', 'scheduledLesson');
    }

    #[Test]
    public function professor_can_confirm_their_scheduled_lesson(): void
    {
        $school = School::factory()->create();
        $professor = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);
        ['scheduledLesson' => $sl, 'package' => $package, 'student' => $student] = $this->setupClassWithStudent($school, $professor);

        $response = $this->actingAs($professor)
            ->post(route('scheduled-lessons.confirm', $sl), [
                'notes' => 'Aula com foco em present perfect.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('scheduled_lessons', [
            'id' => $sl->id,
            'status' => 'confirmed',
        ]);

        $this->assertDatabaseHas('lessons', [
            'student_id' => $student->id,
            'professor_id' => $professor->id,
            'package_id' => $package->id,
        ]);

        $package->refresh();
        $this->assertSame(1, $package->used_lessons);
    }

    #[Test]
    public function school_admin_can_confirm_scheduled_lesson(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'school_admin', 'school_id' => $school->id]);
        $professor = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);
        ['scheduledLesson' => $sl, 'package' => $package] = $this->setupClassWithStudent($school, $professor);

        $response = $this->actingAs($admin)
            ->post(route('scheduled-lessons.confirm', $sl), []);

        $response->assertRedirect();
        $this->assertDatabaseHas('scheduled_lessons', [
            'id' => $sl->id,
            'status' => 'confirmed',
        ]);

        $package->refresh();
        $this->assertSame(1, $package->used_lessons);
    }

    #[Test]
    public function professor_cannot_confirm_scheduled_lesson_of_another_professors_class(): void
    {
        $school = School::factory()->create();
        $owner = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);
        $intruder = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);
        ['scheduledLesson' => $sl, 'package' => $package] = $this->setupClassWithStudent($school, $owner);

        $response = $this->actingAs($intruder)
            ->post(route('scheduled-lessons.confirm', $sl), []);

        $response->assertForbidden();
        $this->assertDatabaseHas('scheduled_lessons', [
            'id' => $sl->id,
            'status' => 'scheduled',
        ]);
        $package->refresh();
        $this->assertSame(0, $package->used_lessons);
    }

    #[Test]
    public function aluno_cannot_confirm_scheduled_lesson(): void
    {
        $school = School::factory()->create();
        $professor = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);
        ['scheduledLesson' => $sl, 'student' => $student, 'package' => $package] = $this->setupClassWithStudent($school, $professor);

        $response = $this->actingAs($student)
            ->post(route('scheduled-lessons.confirm', $sl), []);

        $response->assertForbidden();
        $this->assertDatabaseHas('scheduled_lessons', [
            'id' => $sl->id,
            'status' => 'scheduled',
        ]);
        $package->refresh();
        $this->assertSame(0, $package->used_lessons);
    }

    #[Test]
    public function confirming_when_student_has_no_active_package_keeps_slot_scheduled(): void
    {
        $school = School::factory()->create();
        $professor = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);
        ['scheduledLesson' => $sl, 'student' => $student, 'package' => $package] = $this->setupClassWithStudent($school, $professor);

        // Exhaust the package: bump used_lessons to total via Action-equivalent path.
        // Direct DB write is acceptable here because we're simulating a pre-existing exhausted state.
        $package->forceFill(['used_lessons' => $package->total_lessons])->save();

        $response = $this->actingAs($professor)
            ->post(route('scheduled-lessons.confirm', $sl), []);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('scheduled_lessons', [
            'id' => $sl->id,
            'status' => 'scheduled',
        ]);
    }

    #[Test]
    public function confirming_an_already_confirmed_slot_returns_error(): void
    {
        $school = School::factory()->create();
        $professor = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);
        ['scheduledLesson' => $sl] = $this->setupClassWithStudent($school, $professor);

        // First confirmation succeeds
        $this->actingAs($professor)->post(route('scheduled-lessons.confirm', $sl), [])->assertRedirect();
        $sl->refresh();
        $this->assertSame('confirmed', $sl->status);

        // Second confirmation must fail with a flash error rather than a 500
        $response = $this->actingAs($professor)->post(route('scheduled-lessons.confirm', $sl), []);
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
