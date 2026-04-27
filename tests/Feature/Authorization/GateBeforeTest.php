<?php

namespace Tests\Feature\Authorization;

use App\Models\ExerciseList;
use App\Models\Lesson;
use App\Models\LessonPackage;
use App\Models\Material;
use App\Models\Payment;
use App\Models\Schedule;
use App\Models\ScheduledLesson;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifies the global Gate::before hook in AppServiceProvider grants every
 * ability to super_admin without each policy needing its own bypass.
 *
 * This test exists because per-policy `before()` hooks were removed in favour
 * of the centralised Gate::before. If someone reintroduces a policy without a
 * super_admin escape hatch, this test fails fast — covering the regression
 * the centralisation was meant to prevent.
 */
class GateBeforeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    #[Test]
    public function super_admin_bypasses_all_policies(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'school_id' => null,
        ]);

        // Build one record of each policy-protected model. None of them belong
        // to the super_admin, so without the bypass every check would deny.
        $turmaClass = TurmaClass::factory()->create();
        $package = LessonPackage::factory()->create();
        $lesson = Lesson::factory()->create();
        $material = Material::factory()->create();
        $payment = Payment::factory()->create();
        $exerciseList = ExerciseList::factory()->create();
        $schedule = Schedule::factory()->create();
        $scheduledLesson = ScheduledLesson::factory()->create();

        $this->actingAs($superAdmin);

        // TurmaClass — view, create, update, delete
        $this->assertTrue(Gate::allows('view', $turmaClass));
        $this->assertTrue(Gate::allows('create', TurmaClass::class));
        $this->assertTrue(Gate::allows('update', $turmaClass));
        $this->assertTrue(Gate::allows('delete', $turmaClass));

        // Lesson — create (needs class context), delete
        $this->assertTrue(Gate::allows('create', [Lesson::class, $turmaClass]));
        $this->assertTrue(Gate::allows('delete', $lesson));

        // Material — create, delete, download
        $this->assertTrue(Gate::allows('create', [Material::class, $turmaClass]));
        $this->assertTrue(Gate::allows('delete', $material));
        $this->assertTrue(Gate::allows('download', $material));

        // Payment — viewAny, create, delete (the policy denies these by default)
        $this->assertTrue(Gate::allows('viewAny', Payment::class));
        $this->assertTrue(Gate::allows('create', Payment::class));
        $this->assertTrue(Gate::allows('delete', $payment));

        // ExerciseList — create, view, delete (uses turmaClass context for some)
        $this->assertTrue(Gate::allows('viewAny', [ExerciseList::class, $turmaClass]));
        $this->assertTrue(Gate::allows('view', [$exerciseList, $turmaClass]));
        $this->assertTrue(Gate::allows('create', [ExerciseList::class, $turmaClass]));
        $this->assertTrue(Gate::allows('delete', $exerciseList));

        // Schedule — view, create, update, delete
        $this->assertTrue(Gate::allows('view', $schedule));
        $this->assertTrue(Gate::allows('create', Schedule::class));
        $this->assertTrue(Gate::allows('update', $schedule));
        $this->assertTrue(Gate::allows('delete', $schedule));

        // ScheduledLesson — view, confirm, cancel
        $this->assertTrue(Gate::allows('view', $scheduledLesson));
        $this->assertTrue(Gate::allows('confirm', $scheduledLesson));
        $this->assertTrue(Gate::allows('cancel', $scheduledLesson));
    }

    #[Test]
    public function non_super_admin_does_not_get_global_bypass(): void
    {
        // A plain student (aluno) must be subject to the normal policy chain;
        // the global before() must NOT short-circuit non-super_admins.
        $aluno = User::factory()->create(['role' => 'aluno']);
        $turmaClass = TurmaClass::factory()->create();

        $this->actingAs($aluno);

        // Class create is admin-only — aluno must be denied even with the
        // global Gate::before in place (because before() returns null for
        // non-super_admins, so the policy method runs and returns false).
        $this->assertFalse(Gate::allows('create', TurmaClass::class));
        $this->assertFalse(Gate::allows('update', $turmaClass));
        $this->assertFalse(Gate::allows('delete', $turmaClass));
    }
}
