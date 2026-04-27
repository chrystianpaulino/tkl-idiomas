<?php

namespace Tests\Feature\Tenant;

use App\Http\Requests\StoreClassRequest;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cross-tenant IDOR protection tests for class creation.
 *
 * Verifies that POST /classes cannot be exploited to assign a professor from
 * another school as the teacher of the new class. Because the User model is
 * not BelongsToSchool scoped, defense-in-depth is required at both the
 * FormRequest validation layer and the controller guard layer.
 */
#[Group('tenant')]
#[Group('security')]
class ClassCreateCrossTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    #[Test]
    public function school_admin_cannot_create_class_with_professor_from_another_school(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        $adminA = User::factory()->create(['role' => 'school_admin', 'school_id' => $schoolA->id]);
        $professorB = User::factory()->create(['role' => 'professor', 'school_id' => $schoolB->id]);

        $response = $this->actingAs($adminA)
            ->from(route('classes.create'))
            ->post(route('classes.store'), [
                'name' => 'Cross-tenant attempt',
                'professor_id' => $professorB->id,
                'description' => 'Should fail',
            ]);

        $response->assertSessionHasErrors('professor_id');
        $this->assertDatabaseMissing('classes', [
            'name' => 'Cross-tenant attempt',
            'professor_id' => $professorB->id,
        ]);
    }

    #[Test]
    public function school_admin_cannot_create_class_with_aluno_as_professor(): void
    {
        $schoolA = School::factory()->create();

        $adminA = User::factory()->create(['role' => 'school_admin', 'school_id' => $schoolA->id]);
        $studentA = User::factory()->create(['role' => 'aluno', 'school_id' => $schoolA->id]);

        $response = $this->actingAs($adminA)
            ->from(route('classes.create'))
            ->post(route('classes.store'), [
                'name' => 'Wrong-role attempt',
                'professor_id' => $studentA->id,
                'description' => 'Should fail',
            ]);

        $response->assertSessionHasErrors('professor_id');
        $this->assertDatabaseMissing('classes', [
            'name' => 'Wrong-role attempt',
            'professor_id' => $studentA->id,
        ]);
    }

    #[Test]
    public function school_admin_can_create_class_with_professor_from_their_own_school(): void
    {
        $schoolA = School::factory()->create();

        $adminA = User::factory()->create(['role' => 'school_admin', 'school_id' => $schoolA->id]);
        $professorA = User::factory()->create(['role' => 'professor', 'school_id' => $schoolA->id]);

        $response = $this->actingAs($adminA)
            ->from(route('classes.create'))
            ->post(route('classes.store'), [
                'name' => 'Same-school class',
                'professor_id' => $professorA->id,
                'description' => 'Should succeed',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('classes.index'));

        $this->assertDatabaseHas('classes', [
            'name' => 'Same-school class',
            'professor_id' => $professorA->id,
            'school_id' => $schoolA->id,
        ]);
    }

    #[Test]
    public function super_admin_validation_rule_does_not_enforce_same_school_filter(): void
    {
        // Note: POST /classes is gated by middleware role:admin,school_admin,professor,
        // so super_admin cannot reach the route directly today. What we MUST
        // guarantee is that the validation rule itself does not narrow the
        // candidate set by school for super_admin — otherwise a future relaxation
        // of the route gate would silently break cross-tenant operations.
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'school_id' => null]);

        $schoolB = School::factory()->create();

        $professorB = User::factory()->create(['role' => 'professor', 'school_id' => $schoolB->id]);

        $this->actingAs($superAdmin);

        $request = StoreClassRequest::create('/fake', 'POST', [
            'name' => 'Cross-tenant',
            'professor_id' => $professorB->id,
            'description' => null,
        ]);
        $request->setUserResolver(fn () => $superAdmin);

        $validator = validator($request->all(), $request->rules());

        $this->assertFalse(
            $validator->errors()->has('professor_id'),
            'Super admin must be allowed to reference any school\'s professor'
        );
    }
}
