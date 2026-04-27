<?php

namespace Tests\Feature\Routes;

use App\Models\LessonPackage;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for the route Route::post('/admin/users/{student}/packages/{package}/payments')
 * with ->scopeBindings(). Laravel resolves {package} via $student->packages() — the
 * alias must remain on User to keep this URL functional.
 */
class PaymentRouteScopeBindingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    public function test_route_resolves_package_via_student_relationship(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'school_admin', 'school_id' => $school->id]);
        $student = User::factory()->create(['role' => 'aluno', 'school_id' => $school->id]);
        $package = LessonPackage::factory()->create([
            'student_id' => $student->id,
            'school_id' => $school->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.payments.store', ['student' => $student->id, 'package' => $package->id]), [
                'amount' => 220.00,
                'currency' => 'BRL',
                'method' => 'pix',
                'paid_at' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'student_id' => $student->id,
            'lesson_package_id' => $package->id,
        ]);
    }

    public function test_route_404s_when_package_belongs_to_different_student(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'school_admin', 'school_id' => $school->id]);
        $studentA = User::factory()->create(['role' => 'aluno', 'school_id' => $school->id]);
        $studentB = User::factory()->create(['role' => 'aluno', 'school_id' => $school->id]);
        $packageOfB = LessonPackage::factory()->create([
            'student_id' => $studentB->id,
            'school_id' => $school->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.payments.store', ['student' => $studentA->id, 'package' => $packageOfB->id]), [
                'amount' => 100.00,
                'currency' => 'BRL',
                'method' => 'pix',
                'paid_at' => now()->toDateString(),
            ])
            ->assertNotFound();
    }
}
