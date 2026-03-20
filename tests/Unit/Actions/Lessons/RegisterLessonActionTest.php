<?php

namespace Tests\Unit\Actions\Lessons;

use App\Actions\Lessons\RegisterLessonAction;
use App\Models\Lesson;
use App\Models\LessonPackage;
use App\Models\School;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegisterLessonActionTest extends TestCase
{
    use RefreshDatabase;

    private RegisterLessonAction $action;

    private School $school;

    private User $professor;

    private User $student;

    private TurmaClass $turmaClass;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->action = new RegisterLessonAction;
        $this->school = School::factory()->create();

        $this->professor = User::factory()->professor()->create(['school_id' => $this->school->id]);
        $this->student = User::factory()->create(['school_id' => $this->school->id]);
        $this->turmaClass = TurmaClass::factory()->create([
            'professor_id' => $this->professor->id,
            'school_id' => $this->school->id,
        ]);

        app()->instance('tenant.school_id', $this->school->id);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    // ── Happy path ─────────────────────────────────────────────────

    public function test_registers_lesson_and_increments_used_lessons(): void
    {
        $package = LessonPackage::factory()->create([
            'student_id' => $this->student->id,
            'total_lessons' => 10,
            'school_id' => $this->school->id,
            'expires_at' => null,
        ]);

        $lesson = $this->action->execute($this->turmaClass, $this->student, $this->professor, [
            'title' => 'Verb tenses',
        ]);

        $this->assertInstanceOf(Lesson::class, $lesson);
        $this->assertDatabaseHas('lessons', [
            'id' => $lesson->id,
            'class_id' => $this->turmaClass->id,
            'student_id' => $this->student->id,
            'professor_id' => $this->professor->id,
            'package_id' => $package->id,
            'title' => 'Verb tenses',
        ]);
        $this->assertEquals(1, $package->fresh()->used_lessons);
    }

    // ── No active package ──────────────────────────────────────────

    public function test_throws_when_student_has_no_active_package(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->action->execute($this->turmaClass, $this->student, $this->professor, [
            'title' => 'Should fail',
        ]);
    }

    public function test_throws_when_all_packages_are_expired(): void
    {
        LessonPackage::factory()->expired()->create([
            'student_id' => $this->student->id,
            'total_lessons' => 10,
            'school_id' => $this->school->id,
        ]);

        $this->expectException(ModelNotFoundException::class);

        $this->action->execute($this->turmaClass, $this->student, $this->professor, [
            'title' => 'Should fail',
        ]);
    }

    public function test_throws_when_all_packages_are_exhausted(): void
    {
        LessonPackage::factory()->exhausted()->create([
            'student_id' => $this->student->id,
            'total_lessons' => 10,
            'school_id' => $this->school->id,
        ]);

        $this->expectException(ModelNotFoundException::class);

        $this->action->execute($this->turmaClass, $this->student, $this->professor, [
            'title' => 'Should fail',
        ]);
    }

    // ── FIFO package selection ─────────────────────────────────────

    public function test_fifo_consumes_expiring_package_before_never_expiring(): void
    {
        $expiringPackage = LessonPackage::factory()->create([
            'student_id' => $this->student->id,
            'total_lessons' => 5,
            'school_id' => $this->school->id,
            'expires_at' => now()->addMonth(),
        ]);

        $neverExpiringPackage = LessonPackage::factory()->create([
            'student_id' => $this->student->id,
            'total_lessons' => 5,
            'school_id' => $this->school->id,
            'expires_at' => null,
        ]);

        $lesson = $this->action->execute($this->turmaClass, $this->student, $this->professor, [
            'title' => 'FIFO test',
        ]);

        $this->assertEquals($expiringPackage->id, $lesson->package_id);
        $this->assertEquals(1, $expiringPackage->fresh()->used_lessons);
        $this->assertEquals(0, $neverExpiringPackage->fresh()->used_lessons);
    }

    public function test_fifo_consumes_sooner_expiring_package_first(): void
    {
        $soonerPackage = LessonPackage::factory()->create([
            'student_id' => $this->student->id,
            'total_lessons' => 5,
            'school_id' => $this->school->id,
            'expires_at' => now()->addWeek(),
        ]);

        $laterPackage = LessonPackage::factory()->create([
            'student_id' => $this->student->id,
            'total_lessons' => 5,
            'school_id' => $this->school->id,
            'expires_at' => now()->addMonths(3),
        ]);

        $lesson = $this->action->execute($this->turmaClass, $this->student, $this->professor, [
            'title' => 'FIFO expiry order test',
        ]);

        $this->assertEquals($soonerPackage->id, $lesson->package_id);
        $this->assertEquals(1, $soonerPackage->fresh()->used_lessons);
        $this->assertEquals(0, $laterPackage->fresh()->used_lessons);
    }

    // ── Package exhaustion ─────────────────────────────────────────

    public function test_package_becomes_exhausted_after_last_credit(): void
    {
        $package = LessonPackage::factory()->create([
            'student_id' => $this->student->id,
            'total_lessons' => 1,
            'school_id' => $this->school->id,
            'expires_at' => null,
        ]);

        $lesson = $this->action->execute($this->turmaClass, $this->student, $this->professor, [
            'title' => 'Last lesson',
        ]);

        $freshPackage = $package->fresh();
        $this->assertEquals(1, $freshPackage->used_lessons);
        $this->assertTrue($freshPackage->isExhausted());
        $this->assertFalse($freshPackage->isActive());
    }

    // ── Concurrent safety (documentation) ──────────────────────────

    // NOTE: The RegisterLessonAction uses lockForUpdate() within a DB::transaction() to
    // prevent TOCTOU race conditions. If two requests arrive simultaneously for the last
    // remaining credit, only one will succeed -- the other will re-read after the lock and
    // find the package is no longer active, throwing a RuntimeException. True concurrency
    // testing requires multiple processes and is not feasible with SQLite in-memory; this
    // is documented here for coverage awareness.

    // ── Lesson data ────────────────────────────────────────────────

    public function test_lesson_stores_optional_notes_and_conducted_at(): void
    {
        LessonPackage::factory()->create([
            'student_id' => $this->student->id,
            'total_lessons' => 10,
            'school_id' => $this->school->id,
            'expires_at' => null,
        ]);

        $conductedAt = '2026-03-15 14:00:00';

        $lesson = $this->action->execute($this->turmaClass, $this->student, $this->professor, [
            'title' => 'With notes',
            'notes' => 'Student did well',
            'conducted_at' => $conductedAt,
        ]);

        $this->assertEquals('Student did well', $lesson->notes);
        $this->assertEquals($conductedAt, $lesson->conducted_at->toDateTimeString());
    }

    public function test_lesson_defaults_conducted_at_to_now_when_omitted(): void
    {
        LessonPackage::factory()->create([
            'student_id' => $this->student->id,
            'total_lessons' => 10,
            'school_id' => $this->school->id,
            'expires_at' => null,
        ]);

        $lesson = $this->action->execute($this->turmaClass, $this->student, $this->professor, [
            'title' => 'No conducted_at',
        ]);

        $this->assertNotNull($lesson->conducted_at);
    }
}
