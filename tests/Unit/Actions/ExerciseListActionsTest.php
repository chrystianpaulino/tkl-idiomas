<?php

namespace Tests\Unit\Actions;

use App\Actions\ExerciseLists\CreateExerciseListAction;
use App\Actions\ExerciseLists\DeleteExerciseListAction;
use App\Actions\ExerciseLists\SubmitExerciseListAction;
use App\Models\Exercise;
use App\Models\ExerciseAnswer;
use App\Models\ExerciseList;
use App\Models\ExerciseSubmission;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExerciseListActionsTest extends TestCase
{
    use RefreshDatabase;

    // ─── CreateExerciseListAction ──────────────────────────────────────────

    public function test_create_action_creates_exercise_list_with_exercises(): void
    {
        $professor = User::factory()->create(['role' => 'professor']);
        $class = TurmaClass::factory()->create(['professor_id' => $professor->id]);

        $action = new CreateExerciseListAction;
        $list = $action->execute($class, $professor, [
            'title' => 'Lista 1',
            'description' => 'Descricao da lista',
            'due_date' => null,
            'lesson_id' => null,
            'exercises' => [
                ['question' => 'Pergunta A', 'type' => 'text'],
                ['question' => 'Pergunta B', 'type' => 'file'],
            ],
        ]);

        $this->assertInstanceOf(ExerciseList::class, $list);
        $this->assertEquals('Lista 1', $list->title);
        $this->assertEquals($class->id, $list->class_id);
        $this->assertEquals($professor->id, $list->created_by);
        $this->assertCount(2, $list->exercises);
    }

    public function test_create_action_assigns_order_to_exercises(): void
    {
        $professor = User::factory()->create(['role' => 'professor']);
        $class = TurmaClass::factory()->create(['professor_id' => $professor->id]);

        $action = new CreateExerciseListAction;
        $list = $action->execute($class, $professor, [
            'title' => 'Lista',
            'exercises' => [
                ['question' => 'First', 'type' => 'text'],
                ['question' => 'Second', 'type' => 'text'],
                ['question' => 'Third', 'type' => 'text'],
            ],
        ]);

        $exercises = $list->exercises;
        $this->assertEquals(1, $exercises[0]->order);
        $this->assertEquals(2, $exercises[1]->order);
        $this->assertEquals(3, $exercises[2]->order);
    }

    public function test_create_action_sets_due_date(): void
    {
        $professor = User::factory()->create(['role' => 'professor']);
        $class = TurmaClass::factory()->create(['professor_id' => $professor->id]);

        $action = new CreateExerciseListAction;
        $list = $action->execute($class, $professor, [
            'title' => 'Lista',
            'due_date' => '2030-12-31',
            'exercises' => [
                ['question' => 'Q1', 'type' => 'text'],
            ],
        ]);

        $this->assertEquals('2030-12-31', $list->due_date->format('Y-m-d'));
    }

    public function test_create_action_stores_exercises_in_database(): void
    {
        $professor = User::factory()->create(['role' => 'professor']);
        $class = TurmaClass::factory()->create(['professor_id' => $professor->id]);

        (new CreateExerciseListAction)->execute($class, $professor, [
            'title' => 'Lista teste',
            'exercises' => [
                ['question' => 'Q1', 'type' => 'text'],
                ['question' => 'Q2', 'type' => 'file'],
            ],
        ]);

        $this->assertDatabaseCount('exercise_lists', 1);
        $this->assertDatabaseCount('exercises', 2);
        $this->assertDatabaseHas('exercises', ['question' => 'Q1', 'type' => 'text']);
        $this->assertDatabaseHas('exercises', ['question' => 'Q2', 'type' => 'file']);
    }

    // ─── DeleteExerciseListAction ──────────────────────────────────────────

    public function test_delete_action_removes_exercise_list(): void
    {
        $list = ExerciseList::factory()->create();

        (new DeleteExerciseListAction)->execute($list);

        $this->assertModelMissing($list);
    }

    public function test_delete_action_cascades_to_exercises(): void
    {
        $list = ExerciseList::factory()->create();
        Exercise::factory()->count(3)->create(['exercise_list_id' => $list->id]);

        (new DeleteExerciseListAction)->execute($list);

        $this->assertDatabaseCount('exercises', 0);
    }

    public function test_delete_action_cascades_to_submissions_and_answers(): void
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

        (new DeleteExerciseListAction)->execute($list);

        $this->assertDatabaseCount('exercise_lists', 0);
        $this->assertDatabaseCount('exercise_submissions', 0);
        $this->assertDatabaseCount('exercise_answers', 0);
    }

    // ─── SubmitExerciseListAction ──────────────────────────────────────────

    public function test_submit_action_creates_submission_with_answers(): void
    {
        $list = ExerciseList::factory()->create();
        $exercise1 = Exercise::factory()->create(['exercise_list_id' => $list->id, 'order' => 1]);
        $exercise2 = Exercise::factory()->create(['exercise_list_id' => $list->id, 'order' => 2]);
        $student = User::factory()->create(['role' => 'aluno']);

        $action = new SubmitExerciseListAction;
        $submission = $action->execute($list, $student, [
            'answers' => [
                $exercise1->id => ['answer_text' => 'Resposta 1', 'file' => null],
                $exercise2->id => ['answer_text' => 'Resposta 2', 'file' => null],
            ],
        ]);

        $this->assertInstanceOf(ExerciseSubmission::class, $submission);
        $this->assertTrue($submission->completed);
        $this->assertNotNull($submission->submitted_at);
        $this->assertCount(2, $submission->answers);
        $this->assertEquals('Resposta 1', $submission->answers->firstWhere('exercise_id', $exercise1->id)->answer_text);
    }

    public function test_submit_action_updates_existing_submission_on_resubmit(): void
    {
        $list = ExerciseList::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_list_id' => $list->id]);
        $student = User::factory()->create(['role' => 'aluno']);

        $action = new SubmitExerciseListAction;

        // First submission
        $first = $action->execute($list, $student, [
            'answers' => [
                $exercise->id => ['answer_text' => 'First answer', 'file' => null],
            ],
        ]);

        $originalSubmittedAt = $first->fresh()->submitted_at;

        // Re-submit (update answers — submitted_at should NOT change)
        $action->execute($list, $student, [
            'answers' => [
                $exercise->id => ['answer_text' => 'Updated answer', 'file' => null],
            ],
        ]);

        $this->assertDatabaseCount('exercise_submissions', 1);
        $this->assertDatabaseHas('exercise_answers', ['answer_text' => 'Updated answer']);
        $this->assertDatabaseMissing('exercise_answers', ['answer_text' => 'First answer']);

        // Original submission timestamp must be preserved on re-submit
        $this->assertEquals(
            $originalSubmittedAt->toDateTimeString(),
            $first->fresh()->submitted_at->toDateTimeString()
        );
    }

    public function test_submit_action_ignores_exercise_ids_from_other_lists(): void
    {
        $list = ExerciseList::factory()->create();
        $ownExercise = Exercise::factory()->create(['exercise_list_id' => $list->id]);
        $foreignExercise = Exercise::factory()->create(); // belongs to a different list
        $student = User::factory()->create(['role' => 'aluno']);

        $action = new SubmitExerciseListAction;
        $submission = $action->execute($list, $student, [
            'answers' => [
                $ownExercise->id => ['answer_text' => 'Valid answer', 'file' => null],
                $foreignExercise->id => ['answer_text' => 'Injected answer', 'file' => null],
            ],
        ]);

        // Only the answer for the exercise belonging to this list should be saved
        $this->assertCount(1, $submission->answers);
        $this->assertEquals($ownExercise->id, $submission->answers->first()->exercise_id);
        $this->assertDatabaseMissing('exercise_answers', ['exercise_id' => $foreignExercise->id]);
    }

    public function test_submit_action_stores_uploaded_file(): void
    {
        Storage::fake('public');

        $list = ExerciseList::factory()->create();
        $exercise = Exercise::factory()->fileType()->create(['exercise_list_id' => $list->id]);
        $student = User::factory()->create(['role' => 'aluno']);

        $file = UploadedFile::fake()->create('homework.pdf', 100, 'application/pdf');

        $action = new SubmitExerciseListAction;
        $submission = $action->execute($list, $student, [
            'answers' => [
                $exercise->id => ['answer_text' => null, 'file' => $file],
            ],
        ]);

        $answer = $submission->answers->firstWhere('exercise_id', $exercise->id);
        $this->assertNotNull($answer->file_path);
        $this->assertStringStartsWith('exercise-answers/', $answer->file_path);
        Storage::disk('public')->assertExists($answer->file_path);
    }

    public function test_submit_action_replaces_old_file_on_resubmit(): void
    {
        Storage::fake('public');

        $list = ExerciseList::factory()->create();
        $exercise = Exercise::factory()->fileType()->create(['exercise_list_id' => $list->id]);
        $student = User::factory()->create(['role' => 'aluno']);

        $action = new SubmitExerciseListAction;

        // First submission with file A
        $fileA = UploadedFile::fake()->create('first.pdf', 100, 'application/pdf');
        $submission = $action->execute($list, $student, [
            'answers' => [$exercise->id => ['answer_text' => null, 'file' => $fileA]],
        ]);

        $firstPath = $submission->answers->first()->fresh()->file_path;
        Storage::disk('public')->assertExists($firstPath);

        // Re-submit with file B
        $fileB = UploadedFile::fake()->create('second.pdf', 100, 'application/pdf');
        $submission2 = $action->execute($list, $student, [
            'answers' => [$exercise->id => ['answer_text' => null, 'file' => $fileB]],
        ]);

        $secondPath = $submission2->answers->first()->fresh()->file_path;

        // Old file removed, new file stored
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
    }

    public function test_submit_action_marks_submission_as_completed(): void
    {
        $list = ExerciseList::factory()->create();
        $exercise = Exercise::factory()->create(['exercise_list_id' => $list->id]);
        $student = User::factory()->create(['role' => 'aluno']);

        $submission = (new SubmitExerciseListAction)->execute($list, $student, [
            'answers' => [
                $exercise->id => ['answer_text' => 'done', 'file' => null],
            ],
        ]);

        $this->assertTrue($submission->fresh()->completed);
        $this->assertNotNull($submission->fresh()->submitted_at);
    }
}
