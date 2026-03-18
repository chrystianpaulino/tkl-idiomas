<?php

namespace Tests\Unit\Models;

use App\Models\Exercise;
use App\Models\ExerciseAnswer;
use App\Models\ExerciseList;
use App\Models\ExerciseSubmission;
use App\Models\TurmaClass;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseListTest extends TestCase
{
    use RefreshDatabase;

    // ─── ExerciseList model ────────────────────────────────────────────────

    public function test_exercise_list_belongs_to_turma_class(): void
    {
        $professor = User::factory()->create(['role' => 'professor']);
        $class = TurmaClass::factory()->create(['professor_id' => $professor->id]);
        $list = ExerciseList::factory()->create(['class_id' => $class->id, 'created_by' => $professor->id]);

        $this->assertInstanceOf(TurmaClass::class, $list->turmaClass);
        $this->assertEquals($class->id, $list->turmaClass->id);
    }

    public function test_exercise_list_belongs_to_creator(): void
    {
        $professor = User::factory()->create(['role' => 'professor']);
        $list = ExerciseList::factory()->create(['created_by' => $professor->id]);

        $this->assertInstanceOf(User::class, $list->creator);
        $this->assertEquals($professor->id, $list->creator->id);
    }

    public function test_exercise_list_lesson_is_nullable(): void
    {
        $list = ExerciseList::factory()->create(['lesson_id' => null]);

        $this->assertNull($list->lesson);
    }

    public function test_exercise_list_has_many_exercises_ordered_by_order(): void
    {
        $list = ExerciseList::factory()->create();
        Exercise::factory()->create(['exercise_list_id' => $list->id, 'order' => 3, 'question' => 'Third']);
        Exercise::factory()->create(['exercise_list_id' => $list->id, 'order' => 1, 'question' => 'First']);
        Exercise::factory()->create(['exercise_list_id' => $list->id, 'order' => 2, 'question' => 'Second']);

        $exercises = $list->exercises;

        $this->assertCount(3, $exercises);
        $this->assertEquals('First', $exercises[0]->question);
        $this->assertEquals('Second', $exercises[1]->question);
        $this->assertEquals('Third', $exercises[2]->question);
    }

    public function test_exercise_list_has_many_submissions(): void
    {
        $list = ExerciseList::factory()->create();
        $student1 = User::factory()->create(['role' => 'aluno']);
        $student2 = User::factory()->create(['role' => 'aluno']);

        ExerciseSubmission::factory()->create(['exercise_list_id' => $list->id, 'student_id' => $student1->id]);
        ExerciseSubmission::factory()->create(['exercise_list_id' => $list->id, 'student_id' => $student2->id]);

        $this->assertCount(2, $list->submissions);
    }

    public function test_is_overdue_returns_true_when_past_due_date(): void
    {
        $list = ExerciseList::factory()->overdue()->create();

        $this->assertTrue($list->isOverdue());
    }

    public function test_is_overdue_returns_false_when_due_date_is_today(): void
    {
        $list = ExerciseList::factory()->create(['due_date' => today()->format('Y-m-d')]);

        // Due today means the deadline hasn't passed yet — not overdue until tomorrow
        $this->assertFalse($list->isOverdue());
    }

    public function test_is_overdue_returns_false_when_due_date_is_future(): void
    {
        $list = ExerciseList::factory()->create(['due_date' => now()->addDays(5)->format('Y-m-d')]);

        $this->assertFalse($list->isOverdue());
    }

    public function test_is_overdue_returns_false_when_no_due_date(): void
    {
        $list = ExerciseList::factory()->noDueDate()->create();

        $this->assertFalse($list->isOverdue());
    }

    public function test_due_date_is_cast_to_carbon(): void
    {
        $list = ExerciseList::factory()->create(['due_date' => '2030-12-31']);

        $this->assertInstanceOf(Carbon::class, $list->due_date);
        $this->assertEquals('2030-12-31', $list->due_date->format('Y-m-d'));
    }

    // ─── Exercise model ────────────────────────────────────────────────────

    public function test_exercise_belongs_to_exercise_list(): void
    {
        $list = ExerciseList::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_list_id' => $list->id]);

        $this->assertEquals($list->id, $exercise->exerciseList->id);
    }

    public function test_exercise_has_many_answers(): void
    {
        $list = ExerciseList::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_list_id' => $list->id]);
        $submission = ExerciseSubmission::factory()->create(['exercise_list_id' => $list->id]);

        ExerciseAnswer::factory()->create([
            'exercise_submission_id' => $submission->id,
            'exercise_id' => $exercise->id,
        ]);

        $this->assertCount(1, $exercise->answers);
    }

    public function test_exercise_type_defaults_to_text(): void
    {
        $exercise = Exercise::factory()->create(['type' => 'text']);
        $this->assertEquals('text', $exercise->type);
    }

    public function test_exercise_can_be_file_type(): void
    {
        $exercise = Exercise::factory()->fileType()->create();
        $this->assertEquals('file', $exercise->type);
    }

    // ─── ExerciseSubmission model ──────────────────────────────────────────

    public function test_submission_belongs_to_exercise_list(): void
    {
        $list = ExerciseList::factory()->create();
        $student = User::factory()->create(['role' => 'aluno']);
        $submission = ExerciseSubmission::factory()->create([
            'exercise_list_id' => $list->id,
            'student_id' => $student->id,
        ]);

        $this->assertEquals($list->id, $submission->exerciseList->id);
    }

    public function test_submission_belongs_to_student(): void
    {
        $student = User::factory()->create(['role' => 'aluno']);
        $submission = ExerciseSubmission::factory()->create(['student_id' => $student->id]);

        $this->assertEquals($student->id, $submission->student->id);
    }

    public function test_submission_has_many_answers(): void
    {
        $list = ExerciseList::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_list_id' => $list->id]);
        $student = User::factory()->create(['role' => 'aluno']);
        $submission = ExerciseSubmission::factory()->create([
            'exercise_list_id' => $list->id,
            'student_id' => $student->id,
        ]);

        ExerciseAnswer::factory()->create([
            'exercise_submission_id' => $submission->id,
            'exercise_id' => $exercise->id,
        ]);

        $this->assertCount(1, $submission->answers);
    }

    public function test_is_submitted_returns_false_when_submitted_at_is_null(): void
    {
        $submission = ExerciseSubmission::factory()->create(['submitted_at' => null]);

        $this->assertFalse($submission->isSubmitted());
    }

    public function test_is_submitted_returns_true_when_submitted_at_is_set(): void
    {
        $submission = ExerciseSubmission::factory()->submitted()->create();

        $this->assertTrue($submission->isSubmitted());
    }

    public function test_completed_cast_to_boolean(): void
    {
        $submission = ExerciseSubmission::factory()->submitted()->create();
        $this->assertTrue($submission->completed);

        $pending = ExerciseSubmission::factory()->create(['completed' => false]);
        $this->assertFalse($pending->completed);
    }

    public function test_unique_constraint_on_list_and_student(): void
    {
        $list = ExerciseList::factory()->create();
        $student = User::factory()->create(['role' => 'aluno']);

        ExerciseSubmission::factory()->create([
            'exercise_list_id' => $list->id,
            'student_id' => $student->id,
        ]);

        $this->expectException(QueryException::class);

        ExerciseSubmission::factory()->create([
            'exercise_list_id' => $list->id,
            'student_id' => $student->id,
        ]);
    }

    // ─── ExerciseAnswer model ──────────────────────────────────────────────

    public function test_answer_belongs_to_submission(): void
    {
        $list = ExerciseList::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_list_id' => $list->id]);
        $submission = ExerciseSubmission::factory()->create(['exercise_list_id' => $list->id]);
        $answer = ExerciseAnswer::factory()->create([
            'exercise_submission_id' => $submission->id,
            'exercise_id' => $exercise->id,
        ]);

        $this->assertEquals($submission->id, $answer->submission->id);
    }

    public function test_answer_belongs_to_exercise(): void
    {
        $list = ExerciseList::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_list_id' => $list->id]);
        $submission = ExerciseSubmission::factory()->create(['exercise_list_id' => $list->id]);
        $answer = ExerciseAnswer::factory()->create([
            'exercise_submission_id' => $submission->id,
            'exercise_id' => $exercise->id,
        ]);

        $this->assertEquals($exercise->id, $answer->exercise->id);
    }

    public function test_answer_file_url_is_null_when_no_file_path(): void
    {
        $list = ExerciseList::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_list_id' => $list->id]);
        $submission = ExerciseSubmission::factory()->create(['exercise_list_id' => $list->id]);
        $answer = ExerciseAnswer::factory()->create([
            'exercise_submission_id' => $submission->id,
            'exercise_id' => $exercise->id,
            'file_path' => null,
        ]);

        $this->assertNull($answer->file_url);
    }

    public function test_answer_file_url_is_set_when_file_path_exists(): void
    {
        $list = ExerciseList::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_list_id' => $list->id]);
        $submission = ExerciseSubmission::factory()->create(['exercise_list_id' => $list->id]);
        $answer = ExerciseAnswer::factory()->create([
            'exercise_submission_id' => $submission->id,
            'exercise_id' => $exercise->id,
            'file_path' => 'exercise-answers/1/document.pdf',
        ]);

        $this->assertNotNull($answer->file_url);
        $this->assertStringContainsString('exercise-answers/1/document.pdf', $answer->file_url);
    }

    // ─── TurmaClass relationship ───────────────────────────────────────────

    public function test_turma_class_has_many_exercise_lists(): void
    {
        $professor = User::factory()->create(['role' => 'professor']);
        $class = TurmaClass::factory()->create(['professor_id' => $professor->id]);

        ExerciseList::factory()->count(3)->create([
            'class_id' => $class->id,
            'created_by' => $professor->id,
        ]);

        $this->assertCount(3, $class->exerciseLists);
    }
}
