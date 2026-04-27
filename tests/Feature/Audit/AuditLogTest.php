<?php

namespace Tests\Feature\Audit;

use App\Actions\Payments\RegisterPaymentAction;
use App\Actions\Schedules\CancelScheduledLessonAction;
use App\Actions\Schedules\ConfirmScheduledLessonAction;
use App\Actions\Schools\ProvisionSchoolAction;
use App\Actions\Schools\UpdateSchoolAction;
use App\Models\LessonPackage;
use App\Models\Schedule;
use App\Models\ScheduledLesson;
use App\Models\School;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\LogManager;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\AbstractLogger;
use Tests\TestCase;

/**
 * In-memory logger used by AuditLogTest to capture audit events without
 * touching the filesystem. Implements PSR-3 so it satisfies LogManager's
 * channel resolver contract.
 */
class CapturingLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string|\Stringable, context: array}> */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }

    public function reset(): void
    {
        $this->records = [];
    }

    /**
     * Returns true when at least one record matches both event name and
     * an optional predicate against the context payload.
     */
    public function hasEvent(string $event, ?\Closure $check = null): bool
    {
        foreach ($this->records as $record) {
            if ($record['message'] !== $event) {
                continue;
            }
            if (($record['context']['event'] ?? null) !== $event) {
                continue;
            }
            if ($check !== null && ! $check($record['context'])) {
                continue;
            }

            return true;
        }

        return false;
    }
}

/**
 * Verifies the audit logging contract introduced in Wave 8 / Fix M4.
 *
 * Strategy: install a CapturingLogger as the `audit` channel via a tiny
 * subclass of LogManager. We never touch the physical log file -- the
 * captured records live in-memory and are inspected after each scenario.
 * Other channels keep their original behaviour.
 *
 * Sensitive payloads (passwords, tokens) are intentionally checked here too:
 * the Audit facade redacts a known list of keys and these tests pin that
 * contract by sending those keys deliberately.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private CapturingLogger $auditLogger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auditLogger = new CapturingLogger;

        // Wrap the existing LogManager so Log::channel('audit') returns our
        // capturing logger; every other channel is delegated to the original
        // (avoids breaking exception logging in the framework).
        $original = $this->app['log'];
        $this->app->instance('log', new class($this->app, $original, $this->auditLogger) extends LogManager
        {
            public function __construct($app, private LogManager $delegate, private CapturingLogger $auditLogger)
            {
                parent::__construct($app);
            }

            public function channel($channel = null)
            {
                if ($channel === 'audit') {
                    return $this->auditLogger;
                }

                return $this->delegate->channel($channel);
            }

            public function driver($driver = null)
            {
                return $this->delegate->driver($driver);
            }

            public function getDefaultDriver()
            {
                return $this->delegate->getDefaultDriver();
            }

            public function __call($method, $parameters)
            {
                return $this->delegate->{$method}(...$parameters);
            }
        });
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    private function assertAuditLogged(string $event, ?\Closure $check = null): void
    {
        $this->assertTrue(
            $this->auditLogger->hasEvent($event, $check),
            "Expected audit event '{$event}' was not logged. Captured: ".json_encode(
                array_map(fn ($r) => ['msg' => $r['message'], 'ctx_event' => $r['context']['event'] ?? null], $this->auditLogger->records)
            )
        );
    }

    private function makeAdmin(?School $school = null): User
    {
        $school ??= School::factory()->create();
        $admin = User::factory()->create(['school_id' => $school->id]);
        $admin->role = 'school_admin';
        $admin->save();

        return $admin;
    }

    private function makeSuperAdmin(): User
    {
        $u = User::factory()->create(['school_id' => null]);
        $u->role = 'super_admin';
        $u->save();

        return $u;
    }

    // ── auth.* events (Login, Failed, Logout) ─────────────────────────────

    #[Test]
    public function login_success_is_audited(): void
    {
        $admin = $this->makeAdmin();
        $admin->password = bcrypt('CorrectHorseBattery!9');
        $admin->save();

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'CorrectHorseBattery!9',
        ]);

        $this->assertAuditLogged('auth.login.success', function (array $ctx) use ($admin) {
            return ($ctx['user_id'] ?? null) === $admin->id
                && ($ctx['email'] ?? null) === $admin->email
                && ! isset($ctx['password']);
        });
    }

    #[Test]
    public function login_failed_is_audited(): void
    {
        $admin = $this->makeAdmin();
        $admin->password = bcrypt('correct-password!9');
        $admin->save();

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'wrong-password!9',
        ]);

        $this->assertAuditLogged('auth.login.failed', function (array $ctx) use ($admin) {
            return ($ctx['email'] ?? null) === $admin->email
                && ! isset($ctx['password']);
        });
    }

    #[Test]
    public function logout_is_audited(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post('/logout');

        $this->assertAuditLogged('auth.logout', fn (array $ctx) => ($ctx['user_id'] ?? null) === $admin->id);
    }

    // ── user.* events ─────────────────────────────────────────────────────

    #[Test]
    public function user_created_is_audited(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Aluno Audit',
            'email' => 'aluno.audit@example.com',
            'password' => 'StrongPass!2026',
            'password_confirmation' => 'StrongPass!2026',
            'role' => 'aluno',
        ]);

        $this->assertAuditLogged('user.created', function (array $ctx) use ($admin) {
            return ($ctx['target_email'] ?? null) === 'aluno.audit@example.com'
                && ($ctx['target_role'] ?? null) === 'aluno'
                && ($ctx['target_school_id'] ?? null) === $admin->school_id
                && ! isset($ctx['password']);
        });
    }

    #[Test]
    public function user_updated_is_audited(): void
    {
        $admin = $this->makeAdmin();
        $aluno = User::factory()->create(['school_id' => $admin->school_id]);
        $aluno->role = 'aluno';
        $aluno->save();

        $this->actingAs($admin)->put(route('admin.users.update', $aluno), [
            'name' => 'Aluno Renamed',
            'email' => $aluno->email,
            'role' => 'aluno',
        ]);

        $this->assertAuditLogged('user.updated', function (array $ctx) use ($aluno) {
            return ($ctx['target_user_id'] ?? null) === $aluno->id
                && in_array('name', $ctx['changed_fields'] ?? [], true);
        });
    }

    #[Test]
    public function user_role_changed_is_audited(): void
    {
        $admin = $this->makeAdmin();
        $aluno = User::factory()->create(['school_id' => $admin->school_id]);
        $aluno->role = 'aluno';
        $aluno->save();

        $this->actingAs($admin)->put(route('admin.users.update', $aluno), [
            'name' => $aluno->name,
            'email' => $aluno->email,
            'role' => 'professor',
        ]);

        $this->assertAuditLogged('user.role_changed', function (array $ctx) use ($aluno) {
            return ($ctx['target_user_id'] ?? null) === $aluno->id
                && ($ctx['old_role'] ?? null) === 'aluno'
                && ($ctx['new_role'] ?? null) === 'professor';
        });
    }

    #[Test]
    public function user_deleted_is_audited(): void
    {
        $admin = $this->makeAdmin();
        $aluno = User::factory()->create(['school_id' => $admin->school_id]);
        $aluno->role = 'aluno';
        $aluno->save();

        $this->actingAs($admin)->delete(route('admin.users.destroy', $aluno));

        $this->assertAuditLogged('user.deleted', function (array $ctx) use ($aluno) {
            return ($ctx['target_user_id'] ?? null) === $aluno->id
                && ($ctx['target_role'] ?? null) === 'aluno';
        });
    }

    // ── school.* events ───────────────────────────────────────────────────

    #[Test]
    public function school_provisioned_is_audited(): void
    {
        app(ProvisionSchoolAction::class)->execute([
            'name' => 'Audit Trail School',
            'slug' => 'audit-trail-school',
            'admin_name' => 'First Admin',
            'admin_email' => 'first.admin@audit-trail.test',
            'admin_password' => 'StrongPass!2026',
        ]);

        $this->assertAuditLogged('school.provisioned', function (array $ctx) {
            return ($ctx['slug'] ?? null) === 'audit-trail-school'
                && ($ctx['admin_email'] ?? null) === 'first.admin@audit-trail.test'
                && ! isset($ctx['admin_password']);
        });
    }

    #[Test]
    public function school_updated_is_audited(): void
    {
        $school = School::factory()->create(['name' => 'Original Name']);

        app(UpdateSchoolAction::class)->execute($school, ['name' => 'Renamed School']);

        $this->assertAuditLogged('school.updated', function (array $ctx) use ($school) {
            return ($ctx['school_id'] ?? null) === $school->id
                && in_array('name', $ctx['changed_fields'] ?? [], true);
        });
    }

    #[Test]
    public function school_deleted_is_audited(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $school = School::factory()->create(['name' => 'About to die']);

        $this->actingAs($superAdmin)->delete(route('platform.schools.destroy', $school));

        $this->assertAuditLogged('school.deleted', function (array $ctx) use ($school) {
            return ($ctx['school_id'] ?? null) === $school->id
                && ($ctx['name'] ?? null) === 'About to die';
        });
    }

    // ── payment.registered ────────────────────────────────────────────────

    #[Test]
    public function payment_registered_is_audited(): void
    {
        // We exercise RegisterPaymentAction directly rather than via the HTTP
        // route. The route uses ->scopeBindings() which expects a User::packages()
        // relation that does not exist in this codebase (the relation is named
        // lessonPackages); hitting the URL would raise a BadMethodCallException
        // unrelated to the audit contract under test. Driving the action
        // directly keeps this test focused on the audit emission.
        $admin = $this->makeAdmin();
        $this->actingAs($admin); // populates Auth::user() so Audit captures actor_id

        $student = User::factory()->create(['school_id' => $admin->school_id]);
        $student->role = 'aluno';
        $student->save();

        $package = LessonPackage::factory()->create([
            'student_id' => $student->id,
            'school_id' => $admin->school_id,
        ]);

        $action = app(RegisterPaymentAction::class);
        $action->execute(
            $student,
            $package,
            [
                'amount' => 250.00,
                'currency' => 'BRL',
                'method' => 'pix',
                'paid_at' => now()->toDateString(),
            ],
            $admin->id
        );

        $this->assertAuditLogged('payment.registered', function (array $ctx) use ($student, $package, $admin) {
            return ($ctx['student_id'] ?? null) === $student->id
                && ($ctx['package_id'] ?? null) === $package->id
                && ($ctx['method'] ?? null) === 'pix'
                && ($ctx['currency'] ?? null) === 'BRL'
                && ($ctx['registered_by'] ?? null) === $admin->id;
        });
    }

    // ── lesson.scheduled_* events ─────────────────────────────────────────

    private function makeScheduledLesson(User $admin, User $professor): ScheduledLesson
    {
        $turmaClass = TurmaClass::factory()->create([
            'school_id' => $admin->school_id,
            'professor_id' => $professor->id,
        ]);

        $student = User::factory()->create(['school_id' => $admin->school_id]);
        $student->role = 'aluno';
        $student->save();
        $turmaClass->students()->attach($student->id);

        LessonPackage::factory()->create([
            'student_id' => $student->id,
            'school_id' => $admin->school_id,
        ]);

        $schedule = Schedule::factory()->create([
            'class_id' => $turmaClass->id,
        ]);

        $scheduled = new ScheduledLesson;
        $scheduled->schedule_id = $schedule->id;
        $scheduled->class_id = $turmaClass->id;
        $scheduled->school_id = $admin->school_id;
        $scheduled->scheduled_at = now()->addDay();
        $scheduled->status = 'scheduled';
        $scheduled->save();

        return $scheduled;
    }

    #[Test]
    public function lesson_scheduled_confirmed_is_audited(): void
    {
        $admin = $this->makeAdmin();
        $professor = User::factory()->create(['school_id' => $admin->school_id]);
        $professor->role = 'professor';
        $professor->save();

        $scheduled = $this->makeScheduledLesson($admin, $professor);

        // Bind tenant for the scoped Lesson::create() in RegisterLessonAction
        // (BelongsToSchool auto-fills school_id when a tenant is bound).
        app()->instance('tenant.school_id', $admin->school_id);

        app(ConfirmScheduledLessonAction::class)->execute($scheduled, $professor);

        $this->assertAuditLogged('lesson.scheduled_confirmed', function (array $ctx) use ($scheduled, $professor) {
            return ($ctx['scheduled_lesson_id'] ?? null) === $scheduled->id
                && ($ctx['professor_id'] ?? null) === $professor->id
                && ($ctx['lesson_count'] ?? 0) >= 1;
        });
    }

    #[Test]
    public function lesson_scheduled_cancelled_is_audited(): void
    {
        $admin = $this->makeAdmin();
        $professor = User::factory()->create(['school_id' => $admin->school_id]);
        $professor->role = 'professor';
        $professor->save();

        $scheduled = $this->makeScheduledLesson($admin, $professor);

        app(CancelScheduledLessonAction::class)->execute($scheduled, 'doente');

        $this->assertAuditLogged('lesson.scheduled_cancelled', function (array $ctx) use ($scheduled) {
            return ($ctx['scheduled_lesson_id'] ?? null) === $scheduled->id
                && ($ctx['reason'] ?? null) === 'doente';
        });
    }
}
