<?php

namespace Tests\Unit\Actions;

use App\Actions\GetProgressStatsAction;
use App\Models\Lesson;
use App\Models\LessonPackage;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetProgressStatsActionTest extends TestCase
{
    use RefreshDatabase;

    private function createLessonForStudent(User $student, array $attributes = []): Lesson
    {
        $professor = User::factory()->professor()->create();
        $package = LessonPackage::factory()->create(['student_id' => $student->id]);
        $turmaClass = TurmaClass::factory()->create(['professor_id' => $professor->id]);

        return Lesson::factory()->create(array_merge([
            'student_id' => $student->id,
            'professor_id' => $professor->id,
            'class_id' => $turmaClass->id,
            'package_id' => $package->id,
            'status' => 'completed',
            'conducted_at' => now(),
        ], $attributes));
    }

    public function test_returns_zero_stats_for_student_with_no_lessons(): void
    {
        $student = User::factory()->create();

        $result = (new GetProgressStatsAction)->execute($student);

        $this->assertEquals(0, $result['lessonsCompleted']);
        $this->assertEquals(0.0, $result['hoursStudied']);
        $this->assertEquals(0, $result['currentStreak']);
        $this->assertEquals(10, $result['nextMilestone']);
        $this->assertEquals(0, $result['milestoneProgress']);
    }

    public function test_counts_only_completed_lessons(): void
    {
        $student = User::factory()->create();

        $this->createLessonForStudent($student, ['status' => 'completed']);
        $this->createLessonForStudent($student, ['status' => 'completed']);
        $this->createLessonForStudent($student, ['status' => 'cancelled']);
        $this->createLessonForStudent($student, ['status' => 'absent_excused']);

        $result = (new GetProgressStatsAction)->execute($student);

        $this->assertEquals(2, $result['lessonsCompleted']);
    }

    public function test_hours_studied_defaults_to_one_hour_per_lesson_without_schedule(): void
    {
        $student = User::factory()->create();

        $this->createLessonForStudent($student, ['status' => 'completed']);
        $this->createLessonForStudent($student, ['status' => 'completed']);
        $this->createLessonForStudent($student, ['status' => 'completed']);

        $result = (new GetProgressStatsAction)->execute($student);

        $this->assertEquals(3.0, $result['hoursStudied']);
    }

    public function test_calculates_streak_of_one_for_single_recent_lesson(): void
    {
        $student = User::factory()->create();

        $this->createLessonForStudent($student, [
            'status' => 'completed',
            'conducted_at' => now(),
        ]);

        $result = (new GetProgressStatsAction)->execute($student);

        $this->assertEquals(1, $result['currentStreak']);
    }

    public function test_streak_resets_when_week_gap_exists(): void
    {
        $student = User::factory()->create();

        $this->createLessonForStudent($student, [
            'status' => 'completed',
            'conducted_at' => now()->subWeeks(3),
        ]);

        $result = (new GetProgressStatsAction)->execute($student);

        $this->assertEquals(0, $result['currentStreak']);
    }

    public function test_next_milestone_is_10_for_new_student(): void
    {
        $student = User::factory()->create();

        $result = (new GetProgressStatsAction)->execute($student);

        $this->assertEquals(10, $result['nextMilestone']);
        $this->assertEquals(0, $result['milestoneProgress']);
    }

    public function test_next_milestone_advances_after_passing_10(): void
    {
        $student = User::factory()->create();

        for ($i = 0; $i < 12; $i++) {
            $this->createLessonForStudent($student, ['status' => 'completed']);
        }

        $result = (new GetProgressStatsAction)->execute($student);

        $this->assertEquals(20, $result['nextMilestone']);
        $this->assertEquals(20, $result['milestoneProgress']); // (12-10)/(20-10) * 100 = 20
    }

    public function test_milestone_progress_is_100_when_beyond_all_milestones(): void
    {
        $student = User::factory()->create();

        for ($i = 0; $i < 250; $i++) {
            $this->createLessonForStudent($student, ['status' => 'completed']);
        }

        $result = (new GetProgressStatsAction)->execute($student);

        $this->assertNull($result['nextMilestone']);
        $this->assertEquals(100, $result['milestoneProgress']);
    }
}
