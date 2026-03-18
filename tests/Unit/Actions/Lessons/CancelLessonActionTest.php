<?php

namespace Tests\Unit\Actions\Lessons;

use App\Actions\Lessons\CancelLessonAction;
use App\Models\Lesson;
use App\Models\LessonPackage;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelLessonActionTest extends TestCase
{
    use RefreshDatabase;

    private function makeLesson(string $status, ?int $usedLessons = 3): array
    {
        $professor = User::factory()->professor()->create();
        $student = User::factory()->create();
        $package = LessonPackage::factory()->create([
            'student_id' => $student->id,
            'total_lessons' => 10,
        ]);
        // Set used_lessons directly (not in fillable)
        $package->used_lessons = $usedLessons;
        $package->save();

        $turmaClass = TurmaClass::factory()->create(['professor_id' => $professor->id]);

        $lesson = Lesson::factory()->create([
            'student_id' => $student->id,
            'professor_id' => $professor->id,
            'class_id' => $turmaClass->id,
            'package_id' => $package->id,
            'status' => $status,
        ]);

        return compact('lesson', 'package', 'student');
    }

    public function test_refund_decrements_used_lessons_for_completed_lesson(): void
    {
        ['lesson' => $lesson, 'package' => $package] = $this->makeLesson('completed', 3);

        $result = (new CancelLessonAction)->execute($lesson, refundLesson: true);

        $this->assertEquals('cancelled', $result->status);
        $this->assertEquals(2, $package->fresh()->used_lessons);
    }

    public function test_refund_decrements_for_absent_unexcused_lesson(): void
    {
        ['lesson' => $lesson, 'package' => $package] = $this->makeLesson('absent_unexcused', 5);

        (new CancelLessonAction)->execute($lesson, refundLesson: true);

        $this->assertEquals(4, $package->fresh()->used_lessons);
    }

    public function test_refund_decrements_for_absent_excused_lesson(): void
    {
        ['lesson' => $lesson, 'package' => $package] = $this->makeLesson('absent_excused', 2);

        (new CancelLessonAction)->execute($lesson, refundLesson: true);

        $this->assertEquals(1, $package->fresh()->used_lessons);
    }

    public function test_refund_does_not_decrement_for_scheduled_lesson(): void
    {
        ['lesson' => $lesson, 'package' => $package] = $this->makeLesson('scheduled', 3);

        $result = (new CancelLessonAction)->execute($lesson, refundLesson: true);

        $this->assertEquals('cancelled', $result->status);
        $this->assertEquals(3, $package->fresh()->used_lessons); // unchanged
    }

    public function test_no_refund_marks_lesson_as_absent_unexcused_not_cancelled(): void
    {
        ['lesson' => $lesson, 'package' => $package] = $this->makeLesson('completed', 3);

        $result = (new CancelLessonAction)->execute($lesson, refundLesson: false);

        $this->assertEquals('absent_unexcused', $result->status);
        $this->assertEquals(3, $package->fresh()->used_lessons); // unchanged — no refund
    }

    public function test_throws_when_lesson_is_already_cancelled(): void
    {
        ['lesson' => $lesson] = $this->makeLesson('cancelled', 3);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches("/Cannot cancel a lesson with status 'cancelled'/");

        (new CancelLessonAction)->execute($lesson, refundLesson: true);
    }

    // NOTE: The null package_id guard in CancelLessonAction (package_id !== null check)
    // exists for future schema evolution when scheduled lessons may be created without a
    // pre-assigned package. It cannot be tested now because lessons.package_id is NOT NULL
    // in the current schema. Add a test here when package_id is made nullable.
}
