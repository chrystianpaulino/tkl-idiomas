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

#[Group('schedules')]
class ScheduledLessonCancelTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    private function buildSlot(School $school, User $professor): ScheduledLesson
    {
        $class = TurmaClass::withoutGlobalScope(SchoolScope::class)->create([
            'name' => 'Turma Cancel',
            'professor_id' => $professor->id,
            'school_id' => $school->id,
        ]);

        $student = User::factory()->create(['role' => 'aluno', 'school_id' => $school->id]);
        $class->students()->attach($student->id);

        LessonPackage::withoutGlobalScope(SchoolScope::class)->create([
            'student_id' => $student->id,
            'school_id' => $school->id,
            'total_lessons' => 5,
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

        return ScheduledLesson::withoutGlobalScope(SchoolScope::class)->forceCreate([
            'schedule_id' => $schedule->id,
            'class_id' => $class->id,
            'school_id' => $school->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);
    }

    #[Test]
    public function professor_can_cancel_their_scheduled_lesson_without_debiting_package(): void
    {
        $school = School::factory()->create();
        $professor = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);
        $sl = $this->buildSlot($school, $professor);

        $response = $this->actingAs($professor)
            ->post(route('scheduled-lessons.cancel', $sl), [
                'reason' => 'Feriado municipal',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('scheduled_lessons', [
            'id' => $sl->id,
            'status' => 'cancelled',
            'cancelled_reason' => 'Feriado municipal',
        ]);

        // Pacote NÃO foi debitado
        $package = LessonPackage::withoutGlobalScope(SchoolScope::class)->first();
        $this->assertSame(0, $package->used_lessons);
    }

    #[Test]
    public function school_admin_can_cancel_scheduled_lesson(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'school_admin', 'school_id' => $school->id]);
        $professor = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);
        $sl = $this->buildSlot($school, $professor);

        $response = $this->actingAs($admin)->post(route('scheduled-lessons.cancel', $sl), []);

        $response->assertRedirect();
        $this->assertDatabaseHas('scheduled_lessons', [
            'id' => $sl->id,
            'status' => 'cancelled',
        ]);
    }

    #[Test]
    public function aluno_cannot_cancel_scheduled_lesson(): void
    {
        $school = School::factory()->create();
        $professor = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);
        $sl = $this->buildSlot($school, $professor);

        $student = User::withoutGlobalScopes()
            ->where('role', 'aluno')
            ->where('school_id', $school->id)
            ->first();

        $response = $this->actingAs($student)->post(route('scheduled-lessons.cancel', $sl), []);

        $response->assertForbidden();
        $this->assertDatabaseHas('scheduled_lessons', [
            'id' => $sl->id,
            'status' => 'scheduled',
        ]);
    }
}
