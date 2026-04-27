<?php

namespace Tests\Unit\Models;

use App\Models\LessonPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_remaining_lessons_returns_zero_when_no_packages(): void
    {
        $user = User::factory()->create(['role' => 'aluno']);

        $this->assertEquals(0, $user->remaining_lessons);
    }

    public function test_remaining_lessons_sums_single_active_package(): void
    {
        $user = User::factory()->create(['role' => 'aluno']);
        LessonPackage::factory()->create([
            'student_id' => $user->id,
            'total_lessons' => 10,
            'used_lessons' => 3,
            'expires_at' => null,
        ]);

        $this->assertEquals(7, $user->remaining_lessons);
    }

    public function test_remaining_lessons_sums_multiple_active_packages(): void
    {
        $user = User::factory()->create(['role' => 'aluno']);
        LessonPackage::factory()->create([
            'student_id' => $user->id,
            'total_lessons' => 10,
            'used_lessons' => 3,
            'expires_at' => null,
        ]);
        LessonPackage::factory()->create([
            'student_id' => $user->id,
            'total_lessons' => 5,
            'used_lessons' => 1,
            'expires_at' => null,
        ]);

        $this->assertEquals(11, $user->remaining_lessons);
    }

    public function test_remaining_lessons_excludes_expired_packages(): void
    {
        $user = User::factory()->create(['role' => 'aluno']);
        LessonPackage::factory()->create([
            'student_id' => $user->id,
            'total_lessons' => 10,
            'used_lessons' => 3,
            'expires_at' => null,
        ]);
        LessonPackage::factory()->expired()->create([
            'student_id' => $user->id,
            'total_lessons' => 5,
            'used_lessons' => 0,
        ]);

        $this->assertEquals(7, $user->remaining_lessons);
    }

    public function test_remaining_lessons_excludes_exhausted_packages(): void
    {
        $user = User::factory()->create(['role' => 'aluno']);
        LessonPackage::factory()->create([
            'student_id' => $user->id,
            'total_lessons' => 10,
            'used_lessons' => 3,
            'expires_at' => null,
        ]);
        LessonPackage::factory()->exhausted()->create([
            'student_id' => $user->id,
            'total_lessons' => 5,
        ]);

        $this->assertEquals(7, $user->remaining_lessons);
    }

    public function test_role_helpers_return_correct_boolean(): void
    {
        $schoolAdmin = User::factory()->schoolAdmin()->make();
        $professor = User::factory()->professor()->make();
        $aluno = User::factory()->make(['role' => 'aluno']);

        $this->assertTrue($schoolAdmin->isAdmin());
        $this->assertFalse($schoolAdmin->isProfessor());
        $this->assertFalse($schoolAdmin->isAluno());

        $this->assertTrue($professor->isProfessor());
        $this->assertFalse($professor->isAdmin());

        $this->assertTrue($aluno->isAluno());
        $this->assertFalse($aluno->isAdmin());
    }
}
