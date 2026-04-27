<?php

namespace Tests\Feature\Tenant;

use App\Http\Requests\UpdateClassRequest;
use App\Models\School;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cross-tenant IDOR protection tests for PUT /classes/{class}.
 *
 * Mirrors ClassCreateCrossTenantTest. Verifies UpdateClassRequest filters
 * `professor_id` by school for non-super_admin actors so a school_admin from
 * Escola A cannot reassign their own class to a professor from Escola B
 * (which would have been silently accepted prior to this fix).
 */
#[Group('tenant')]
#[Group('security')]
class ClassUpdateCrossTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    #[Test]
    public function school_admin_cannot_update_class_with_professor_from_another_school(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        $adminA = User::factory()->create(['role' => 'school_admin', 'school_id' => $schoolA->id]);
        $professorA = User::factory()->create(['role' => 'professor', 'school_id' => $schoolA->id]);
        $professorB = User::factory()->create(['role' => 'professor', 'school_id' => $schoolB->id]);

        $class = TurmaClass::factory()->create([
            'school_id' => $schoolA->id,
            'professor_id' => $professorA->id,
            'name' => 'Original',
        ]);

        $response = $this->actingAs($adminA)
            ->from(route('classes.edit', $class))
            ->put(route('classes.update', $class), [
                'name' => 'Hijack attempt',
                'professor_id' => $professorB->id,
                'description' => 'Should fail',
            ]);

        $response->assertSessionHasErrors('professor_id');

        // The class was not mutated.
        $class->refresh();
        $this->assertSame('Original', $class->name);
        $this->assertSame($professorA->id, $class->professor_id);
    }

    #[Test]
    public function school_admin_cannot_update_class_with_aluno_as_professor(): void
    {
        $schoolA = School::factory()->create();

        $adminA = User::factory()->create(['role' => 'school_admin', 'school_id' => $schoolA->id]);
        $professorA = User::factory()->create(['role' => 'professor', 'school_id' => $schoolA->id]);
        $studentA = User::factory()->create(['role' => 'aluno', 'school_id' => $schoolA->id]);

        $class = TurmaClass::factory()->create([
            'school_id' => $schoolA->id,
            'professor_id' => $professorA->id,
        ]);

        $response = $this->actingAs($adminA)
            ->from(route('classes.edit', $class))
            ->put(route('classes.update', $class), [
                'name' => 'Wrong-role attempt',
                'professor_id' => $studentA->id,
                'description' => 'Should fail',
            ]);

        $response->assertSessionHasErrors('professor_id');

        $class->refresh();
        $this->assertSame($professorA->id, $class->professor_id);
    }

    #[Test]
    public function school_admin_can_update_class_with_professor_from_their_own_school(): void
    {
        $schoolA = School::factory()->create();

        $adminA = User::factory()->create(['role' => 'school_admin', 'school_id' => $schoolA->id]);
        $oldProfessor = User::factory()->create(['role' => 'professor', 'school_id' => $schoolA->id]);
        $newProfessor = User::factory()->create(['role' => 'professor', 'school_id' => $schoolA->id]);

        $class = TurmaClass::factory()->create([
            'school_id' => $schoolA->id,
            'professor_id' => $oldProfessor->id,
            'name' => 'Old name',
        ]);

        $response = $this->actingAs($adminA)
            ->from(route('classes.edit', $class))
            ->put(route('classes.update', $class), [
                'name' => 'New name',
                'professor_id' => $newProfessor->id,
                'description' => 'Same school move',
            ]);

        $response->assertSessionHasNoErrors();

        $class->refresh();
        $this->assertSame('New name', $class->name);
        $this->assertSame($newProfessor->id, $class->professor_id);
    }

    #[Test]
    public function super_admin_validation_rule_does_not_enforce_same_school_filter(): void
    {
        // Mirrors the same property as in ClassCreateCrossTenantTest: the rule
        // itself must not narrow candidates by school for super_admin, so a
        // future relaxation of the route gate cannot silently break cross-tenant
        // operations.
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'school_id' => null]);

        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        $professorA = User::factory()->create(['role' => 'professor', 'school_id' => $schoolA->id]);
        $professorB = User::factory()->create(['role' => 'professor', 'school_id' => $schoolB->id]);

        $class = TurmaClass::factory()->create([
            'school_id' => $schoolA->id,
            'professor_id' => $professorA->id,
        ]);

        $this->actingAs($superAdmin);

        $request = UpdateClassRequest::create('/fake', 'PUT', [
            'name' => 'Cross-tenant',
            'professor_id' => $professorB->id,
            'description' => null,
        ]);
        $request->setUserResolver(fn () => $superAdmin);

        $validator = validator($request->all(), $request->rules());

        $this->assertFalse(
            $validator->errors()->has('professor_id'),
            'Super admin must be allowed to reference any school\'s professor on update.'
        );
    }
}
