<?php

namespace Tests\Feature\Tenant;

use App\Models\LessonPackage;
use App\Models\School;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cross-tenant IDOR protection tests for POST /classes/{class}/lessons.
 *
 * StoreLessonRequest's `student_id` rule was previously `exists:users,id`
 * with no role/school filter. The LessonController already had a runtime
 * guard, but defence-in-depth at the validation layer prevents the controller
 * from even reaching the action with a tampered student id.
 */
#[Group('tenant')]
#[Group('security')]
class LessonStoreCrossTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    #[Test]
    public function professor_cannot_register_lesson_for_student_from_another_school(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        $professorA = User::factory()->create(['role' => 'professor', 'school_id' => $schoolA->id]);
        $studentB = User::factory()->create(['role' => 'aluno', 'school_id' => $schoolB->id]);

        $class = TurmaClass::factory()->create([
            'school_id' => $schoolA->id,
            'professor_id' => $professorA->id,
        ]);

        $response = $this->actingAs($professorA)
            ->from(route('classes.lessons.create', $class))
            ->post(route('classes.lessons.store', $class), [
                'student_id' => $studentB->id,
                'title' => 'Cross-tenant attempt',
            ]);

        $response->assertSessionHasErrors('student_id');
        $this->assertDatabaseMissing('lessons', [
            'student_id' => $studentB->id,
            'class_id' => $class->id,
        ]);
    }

    #[Test]
    public function professor_cannot_register_lesson_with_professor_id_as_student(): void
    {
        // student_id rule restricts to role=aluno. Sending another professor's
        // id (same school) must still fail validation.
        $schoolA = School::factory()->create();

        $professorA = User::factory()->create(['role' => 'professor', 'school_id' => $schoolA->id]);
        $otherProfessor = User::factory()->create(['role' => 'professor', 'school_id' => $schoolA->id]);

        $class = TurmaClass::factory()->create([
            'school_id' => $schoolA->id,
            'professor_id' => $professorA->id,
        ]);

        $response = $this->actingAs($professorA)
            ->from(route('classes.lessons.create', $class))
            ->post(route('classes.lessons.store', $class), [
                'student_id' => $otherProfessor->id,
                'title' => 'Wrong-role attempt',
            ]);

        $response->assertSessionHasErrors('student_id');
    }

    #[Test]
    public function professor_can_register_lesson_for_enrolled_student_in_same_school(): void
    {
        $schoolA = School::factory()->create();

        $professorA = User::factory()->create(['role' => 'professor', 'school_id' => $schoolA->id]);
        $studentA = User::factory()->create(['role' => 'aluno', 'school_id' => $schoolA->id]);

        $class = TurmaClass::factory()->create([
            'school_id' => $schoolA->id,
            'professor_id' => $professorA->id,
        ]);

        // Enroll the student so the controller's enrollment check passes.
        $class->students()->attach($studentA->id);

        // Give the student an active package so RegisterLessonAction can consume.
        LessonPackage::factory()->create([
            'school_id' => $schoolA->id,
            'student_id' => $studentA->id,
            'total_lessons' => 10,
            'used_lessons' => 0,
        ]);

        $response = $this->actingAs($professorA)
            ->from(route('classes.lessons.create', $class))
            ->post(route('classes.lessons.store', $class), [
                'student_id' => $studentA->id,
                'title' => 'Happy path lesson',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('lessons', [
            'student_id' => $studentA->id,
            'class_id' => $class->id,
            'title' => 'Happy path lesson',
        ]);
    }
}
