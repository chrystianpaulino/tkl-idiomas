<?php

namespace Tests\Feature\Schedules;

use App\Models\Schedule;
use App\Models\School;
use App\Models\Scopes\SchoolScope;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * End-to-end tests for the Schedule CRUD HTTP endpoints.
 *
 * Verifies role-based access (school_admin & professor can create; aluno cannot),
 * cross-tenant validation (admin of school A cannot reference a class of school B),
 * and that professors are constrained to scheduling their own classes.
 */
#[Group('schedules')]
class ScheduleCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    private function classFor(School $school, User $professor): TurmaClass
    {
        return TurmaClass::withoutGlobalScope(SchoolScope::class)->create([
            'name' => "Class of {$school->name}",
            'professor_id' => $professor->id,
            'school_id' => $school->id,
        ]);
    }

    #[Test]
    public function school_admin_can_create_schedule(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'school_admin', 'school_id' => $school->id]);
        $professor = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);
        $class = $this->classFor($school, $professor);

        $response = $this->actingAs($admin)
            ->from(route('schedules.create'))
            ->post(route('schedules.store'), [
                'class_id' => $class->id,
                'weekday' => 1,
                'start_time' => '19:00',
                'duration_minutes' => 60,
                'active' => true,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('schedules.index'));

        $this->assertDatabaseHas('schedules', [
            'class_id' => $class->id,
            'weekday' => 1,
            'start_time' => '19:00',
            'duration_minutes' => 60,
            'active' => 1,
            'school_id' => $school->id,
        ]);
    }

    #[Test]
    public function professor_can_create_schedule_for_their_own_class(): void
    {
        $school = School::factory()->create();
        $professor = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);
        $class = $this->classFor($school, $professor);

        $response = $this->actingAs($professor)
            ->from(route('schedules.create'))
            ->post(route('schedules.store'), [
                'class_id' => $class->id,
                'weekday' => 2,
                'start_time' => '14:30',
                'duration_minutes' => 90,
                'active' => true,
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('schedules', [
            'class_id' => $class->id,
            'weekday' => 2,
            'duration_minutes' => 90,
        ]);
    }

    #[Test]
    public function professor_cannot_create_schedule_for_another_professors_class(): void
    {
        $school = School::factory()->create();
        $professorA = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);
        $professorB = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);
        $classOfB = $this->classFor($school, $professorB);

        $response = $this->actingAs($professorA)
            ->from(route('schedules.create'))
            ->post(route('schedules.store'), [
                'class_id' => $classOfB->id,
                'weekday' => 3,
                'start_time' => '10:00',
                'duration_minutes' => 60,
                'active' => true,
            ]);

        $response->assertSessionHasErrors('class_id');
        $this->assertDatabaseMissing('schedules', [
            'class_id' => $classOfB->id,
            'weekday' => 3,
        ]);
    }

    #[Test]
    public function aluno_cannot_create_schedule(): void
    {
        $school = School::factory()->create();
        $professor = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);
        $student = User::factory()->create(['role' => 'aluno', 'school_id' => $school->id]);
        $class = $this->classFor($school, $professor);

        $response = $this->actingAs($student)
            ->from(route('schedules.index'))
            ->post(route('schedules.store'), [
                'class_id' => $class->id,
                'weekday' => 1,
                'start_time' => '19:00',
                'duration_minutes' => 60,
                'active' => true,
            ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('schedules', ['class_id' => $class->id]);
    }

    #[Test]
    public function school_admin_cannot_create_schedule_for_class_in_another_school(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        $adminA = User::factory()->create(['role' => 'school_admin', 'school_id' => $schoolA->id]);
        $professorB = User::factory()->create(['role' => 'professor', 'school_id' => $schoolB->id]);
        $classB = $this->classFor($schoolB, $professorB);

        $response = $this->actingAs($adminA)
            ->from(route('schedules.create'))
            ->post(route('schedules.store'), [
                'class_id' => $classB->id,
                'weekday' => 1,
                'start_time' => '19:00',
                'duration_minutes' => 60,
                'active' => true,
            ]);

        $response->assertSessionHasErrors('class_id');
        $this->assertDatabaseMissing('schedules', ['class_id' => $classB->id]);
    }

    #[Test]
    public function school_admin_can_update_schedule(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'school_admin', 'school_id' => $school->id]);
        $professor = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);
        $class = $this->classFor($school, $professor);
        $schedule = Schedule::forceCreate([
            'class_id' => $class->id,
            'school_id' => $school->id,
            'weekday' => 1,
            'start_time' => '19:00',
            'duration_minutes' => 60,
            'active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('schedules.edit', $schedule))
            ->put(route('schedules.update', $schedule), [
                'class_id' => $class->id,
                'weekday' => 4,
                'start_time' => '20:00',
                'duration_minutes' => 45,
                'active' => false,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('schedules.index'));

        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'weekday' => 4,
            'start_time' => '20:00',
            'duration_minutes' => 45,
            'active' => 0,
        ]);
    }

    #[Test]
    public function school_admin_can_delete_schedule(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'school_admin', 'school_id' => $school->id]);
        $professor = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);
        $class = $this->classFor($school, $professor);
        $schedule = Schedule::forceCreate([
            'class_id' => $class->id,
            'school_id' => $school->id,
            'weekday' => 1,
            'start_time' => '19:00',
            'duration_minutes' => 60,
            'active' => true,
        ]);

        $response = $this->actingAs($admin)->delete(route('schedules.destroy', $schedule));

        $response->assertRedirect(route('schedules.index'));
        $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
    }
}
