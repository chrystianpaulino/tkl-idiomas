<?php

namespace Tests\Feature\Validation;

use App\Models\Exercise;
use App\Models\ExerciseList;
use App\Models\School;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validates that user-supplied arrays are capped at a sensible upper bound.
 *
 * Without these caps, a single malicious request could push the server into
 * megabyte-scale payload validation and DB inserts (DoS). The cap is 200,
 * which is far above any realistic legitimate use case (a lesson with 200+
 * questions or a submission with 200+ answers is implausible).
 */
class ArrayMaxBoundsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    public function test_exercise_list_creation_rejects_more_than_200_exercises(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'school_admin', 'school_id' => $school->id]);
        $professor = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);
        $class = TurmaClass::factory()->create([
            'school_id' => $school->id,
            'professor_id' => $professor->id,
        ]);

        $exercises = [];
        for ($i = 0; $i < 201; $i++) {
            $exercises[] = ['question' => "Q{$i}", 'type' => 'text'];
        }

        $response = $this->actingAs($admin)
            ->from(route('classes.exercise-lists.create', $class))
            ->post(route('classes.exercise-lists.store', $class), [
                'title' => 'Too many exercises',
                'description' => 'should fail',
                'exercises' => $exercises,
            ]);

        $response->assertSessionHasErrors('exercises');
        $this->assertDatabaseMissing('exercise_lists', ['title' => 'Too many exercises']);
    }

    public function test_exercise_list_creation_accepts_exactly_200_exercises(): void
    {
        $school = School::factory()->create();
        $admin = User::factory()->create(['role' => 'school_admin', 'school_id' => $school->id]);
        $professor = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);
        $class = TurmaClass::factory()->create([
            'school_id' => $school->id,
            'professor_id' => $professor->id,
        ]);

        $exercises = [];
        for ($i = 0; $i < 200; $i++) {
            $exercises[] = ['question' => "Q{$i}", 'type' => 'text'];
        }

        $response = $this->actingAs($admin)
            ->from(route('classes.exercise-lists.create', $class))
            ->post(route('classes.exercise-lists.store', $class), [
                'title' => 'At cap exercises',
                'description' => 'should succeed',
                'exercises' => $exercises,
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('exercise_lists', ['title' => 'At cap exercises']);
    }

    public function test_submission_rejects_more_than_200_answers(): void
    {
        $school = School::factory()->create();
        $aluno = User::factory()->create(['role' => 'aluno', 'school_id' => $school->id]);
        $professor = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);
        $class = TurmaClass::factory()->create([
            'school_id' => $school->id,
            'professor_id' => $professor->id,
        ]);
        $class->students()->attach($aluno->id);

        $list = ExerciseList::factory()->create([
            'class_id' => $class->id,
            'created_by' => $professor->id,
            'school_id' => $school->id,
        ]);

        // Build 201 answers keyed by random exercise ids -- the rule rejects
        // before we ever hit the action's per-exercise allow-list, so the ids
        // need not be real.
        $answers = [];
        for ($i = 0; $i < 201; $i++) {
            $answers[$i + 1] = ['answer_text' => "Answer {$i}"];
        }

        $response = $this->actingAs($aluno)
            ->from(route('classes.exercise-lists.submit', [$class, $list]))
            ->post(route('classes.exercise-lists.submit.store', [$class, $list]), [
                'answers' => $answers,
            ]);

        $response->assertSessionHasErrors('answers');
    }

    public function test_submission_accepts_exactly_200_answers(): void
    {
        $school = School::factory()->create();
        $aluno = User::factory()->create(['role' => 'aluno', 'school_id' => $school->id]);
        $professor = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);
        $class = TurmaClass::factory()->create([
            'school_id' => $school->id,
            'professor_id' => $professor->id,
        ]);
        $class->students()->attach($aluno->id);

        $list = ExerciseList::factory()->create([
            'class_id' => $class->id,
            'created_by' => $professor->id,
            'school_id' => $school->id,
        ]);

        // Create real exercises so the per-exercise allow-list in the action
        // does not silently drop them. We keep it small and pad the array to
        // 200 keys; ids that don't belong to the list are simply ignored by
        // the action without producing validation errors.
        $realExercise = Exercise::create([
            'exercise_list_id' => $list->id,
            'order' => 1,
            'question' => 'Q1',
            'type' => 'text',
        ]);

        $answers = [$realExercise->id => ['answer_text' => 'real answer']];
        // Pad to 200 entries with stub ids; validation only counts the array
        // length, the action filters by valid exercise ids afterwards.
        for ($i = 1; $i < 200; $i++) {
            $answers[100000 + $i] = ['answer_text' => "stub {$i}"];
        }
        $this->assertCount(200, $answers);

        $response = $this->actingAs($aluno)
            ->from(route('classes.exercise-lists.submit', [$class, $list]))
            ->post(route('classes.exercise-lists.submit.store', [$class, $list]), [
                'answers' => $answers,
            ]);

        $response->assertSessionHasNoErrors();
    }
}
