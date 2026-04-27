<?php

namespace Tests\Feature\Tenant;

use App\Http\Requests\EnrollStudentRequest;
use App\Models\School;
use App\Models\Scopes\SchoolScope;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cross-tenant IDOR protection tests for the enrollment endpoint.
 *
 * Verifies that POST /admin/classes/{class}/enroll cannot be exploited to
 * enroll a student from another school. Because the User model is not
 * BelongsToSchool scoped, defense-in-depth is required at both the
 * FormRequest validation layer and the controller guard layer.
 */
#[Group('tenant')]
#[Group('security')]
class EnrollmentCrossTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    /**
     * Create a TurmaClass belonging to the given school, bypassing the global
     * scope so an active tenant context does not interfere.
     */
    private function createClass(School $school, User $professor): TurmaClass
    {
        return TurmaClass::withoutGlobalScope(SchoolScope::class)->create([
            'name' => "Class of {$school->name}",
            'professor_id' => $professor->id,
            'school_id' => $school->id,
        ]);
    }

    #[Test]
    public function school_admin_cannot_enroll_student_from_another_school(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        $adminA = User::factory()->create(['role' => 'school_admin', 'school_id' => $schoolA->id]);
        $professorA = User::factory()->create(['role' => 'professor', 'school_id' => $schoolA->id]);
        $studentB = User::factory()->create(['role' => 'aluno', 'school_id' => $schoolB->id]);

        $classA = $this->createClass($schoolA, $professorA);

        $response = $this->actingAs($adminA)
            ->from(route('classes.show', $classA))
            ->post(route('admin.classes.enroll', $classA), [
                'student_id' => $studentB->id,
            ]);

        // Validation layer rejects (422 in JSON, 302 with errors session in web)
        $response->assertSessionHasErrors('student_id');
        $this->assertDatabaseMissing('class_students', [
            'class_id' => $classA->id,
            'student_id' => $studentB->id,
        ]);
    }

    #[Test]
    public function school_admin_cannot_enroll_user_with_role_professor(): void
    {
        $schoolA = School::factory()->create();

        $adminA = User::factory()->create(['role' => 'school_admin', 'school_id' => $schoolA->id]);
        $professorA = User::factory()->create(['role' => 'professor', 'school_id' => $schoolA->id]);
        $otherProfessorA = User::factory()->create(['role' => 'professor', 'school_id' => $schoolA->id]);

        $classA = $this->createClass($schoolA, $professorA);

        $response = $this->actingAs($adminA)
            ->from(route('classes.show', $classA))
            ->post(route('admin.classes.enroll', $classA), [
                'student_id' => $otherProfessorA->id,
            ]);

        $response->assertSessionHasErrors('student_id');
        $this->assertDatabaseMissing('class_students', [
            'class_id' => $classA->id,
            'student_id' => $otherProfessorA->id,
        ]);
    }

    #[Test]
    public function school_admin_can_enroll_student_from_their_own_school(): void
    {
        $schoolA = School::factory()->create();

        $adminA = User::factory()->create(['role' => 'school_admin', 'school_id' => $schoolA->id]);
        $professorA = User::factory()->create(['role' => 'professor', 'school_id' => $schoolA->id]);
        $studentA = User::factory()->create(['role' => 'aluno', 'school_id' => $schoolA->id]);

        $classA = $this->createClass($schoolA, $professorA);

        $response = $this->actingAs($adminA)
            ->from(route('classes.show', $classA))
            ->post(route('admin.classes.enroll', $classA), [
                'student_id' => $studentA->id,
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('class_students', [
            'class_id' => $classA->id,
            'student_id' => $studentA->id,
        ]);
    }

    #[Test]
    public function super_admin_validation_rule_does_not_enforce_same_school_filter(): void
    {
        // Note: the /admin route group is gated by middleware role:admin,school_admin,
        // so super_admin cannot reach POST /admin/classes/{class}/enroll directly.
        // What we MUST guarantee is that the validation rule itself does not
        // narrow the candidate set by school for super_admin — otherwise a
        // future relaxation of the route gate would silently break cross-tenant
        // operations. Verify this at the Validator level.
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'school_id' => null]);

        $schoolB = School::factory()->create();

        $studentB = User::factory()->create(['role' => 'aluno', 'school_id' => $schoolB->id]);

        $this->actingAs($superAdmin);

        $request = EnrollStudentRequest::create('/fake', 'POST', [
            'student_id' => $studentB->id,
        ]);
        $request->setUserResolver(fn () => $superAdmin);

        $validator = validator($request->all(), $request->rules());

        $this->assertFalse(
            $validator->errors()->has('student_id'),
            'Super admin must be allowed to reference any school\'s student'
        );
    }
}
