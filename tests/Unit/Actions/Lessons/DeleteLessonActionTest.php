<?php

namespace Tests\Unit\Actions\Lessons;

use App\Actions\Lessons\DeleteLessonAction;
use App\Models\Lesson;
use App\Models\LessonPackage;
use App\Models\School;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeleteLessonActionTest extends TestCase
{
    use RefreshDatabase;

    private DeleteLessonAction $action;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new DeleteLessonAction;
        $this->school = School::factory()->create();
        app()->instance('tenant.school_id', $this->school->id);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    private function createLessonWithStatus(string $status, int $usedLessons = 3): array
    {
        $professor = User::factory()->professor()->create(['school_id' => $this->school->id]);
        $student = User::factory()->create(['school_id' => $this->school->id]);
        $package = LessonPackage::factory()->create([
            'student_id' => $student->id,
            'total_lessons' => 10,
            'school_id' => $this->school->id,
        ]);
        $package->used_lessons = $usedLessons;
        $package->save();

        $turmaClass = TurmaClass::factory()->create([
            'professor_id' => $professor->id,
            'school_id' => $this->school->id,
        ]);

        $lesson = Lesson::factory()->create([
            'student_id' => $student->id,
            'professor_id' => $professor->id,
            'class_id' => $turmaClass->id,
            'package_id' => $package->id,
            'status' => $status,
            'school_id' => $this->school->id,
        ]);

        return compact('lesson', 'package');
    }

    // ── Credit-consuming statuses: used_lessons decrements ─────────

    #[DataProvider('creditConsumingStatusesProvider')]
    public function test_deleting_credit_consuming_status_decrements_used_lessons(string $status): void
    {
        ['lesson' => $lesson, 'package' => $package] = $this->createLessonWithStatus($status, 5);

        $this->action->execute($lesson);

        $this->assertEquals(4, $package->fresh()->used_lessons);
    }

    public static function creditConsumingStatusesProvider(): array
    {
        return [
            'completed' => ['completed'],
            'absent_unexcused' => ['absent_unexcused'],
            'absent_excused' => ['absent_excused'],
        ];
    }

    // ── Non-credit-consuming statuses: no decrement ────────────────

    #[DataProvider('nonCreditConsumingStatusesProvider')]
    public function test_deleting_non_credit_consuming_status_does_not_decrement(string $status): void
    {
        ['lesson' => $lesson, 'package' => $package] = $this->createLessonWithStatus($status, 5);

        $this->action->execute($lesson);

        $this->assertEquals(5, $package->fresh()->used_lessons);
    }

    public static function nonCreditConsumingStatusesProvider(): array
    {
        return [
            'cancelled' => ['cancelled'],
            'scheduled' => ['scheduled'],
        ];
    }

    // ── Lesson is actually removed from database ───────────────────

    public function test_lesson_record_is_deleted_from_database(): void
    {
        ['lesson' => $lesson] = $this->createLessonWithStatus('completed', 3);
        $lessonId = $lesson->id;

        $this->action->execute($lesson);

        $this->assertDatabaseMissing('lessons', ['id' => $lessonId]);
    }

    public function test_lesson_deleted_for_non_consuming_status_also_removes_record(): void
    {
        ['lesson' => $lesson] = $this->createLessonWithStatus('cancelled', 3);
        $lessonId = $lesson->id;

        $this->action->execute($lesson);

        $this->assertDatabaseMissing('lessons', ['id' => $lessonId]);
    }

    // ── Null package_id guard ──────────────────────────────────────
    // NOTE: The current schema has package_id as NOT NULL on the lessons table,
    // so we cannot create a lesson with package_id = null through the normal
    // factory flow. The null guard in DeleteLessonAction exists for future schema
    // evolution. When package_id becomes nullable, add a test here verifying
    // that deletion succeeds without any decrement attempt.
}
