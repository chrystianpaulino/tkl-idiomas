<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Tests\TestCase;

class UserRoleHelpersTest extends TestCase
{
    // ── isSuperAdmin() ──────────────────────────────────────────

    public function test_is_super_admin_returns_true_for_super_admin_role(): void
    {
        $user = User::factory()->make(['role' => 'super_admin', 'school_id' => null]);

        $this->assertTrue($user->isSuperAdmin());
    }

    public function test_is_super_admin_returns_false_for_school_admin(): void
    {
        $user = User::factory()->make(['role' => 'school_admin', 'school_id' => null]);

        $this->assertFalse($user->isSuperAdmin());
    }

    public function test_is_super_admin_returns_false_for_professor(): void
    {
        $user = User::factory()->make(['role' => 'professor', 'school_id' => null]);

        $this->assertFalse($user->isSuperAdmin());
    }

    public function test_is_super_admin_returns_false_for_aluno(): void
    {
        $user = User::factory()->make(['role' => 'aluno', 'school_id' => null]);

        $this->assertFalse($user->isSuperAdmin());
    }

    // ── isSchoolAdmin() ─────────────────────────────────────────

    public function test_is_school_admin_returns_true_for_school_admin_role(): void
    {
        $user = User::factory()->make(['role' => 'school_admin', 'school_id' => null]);

        $this->assertTrue($user->isSchoolAdmin());
    }

    public function test_is_school_admin_returns_false_for_super_admin(): void
    {
        $user = User::factory()->make(['role' => 'super_admin', 'school_id' => null]);

        $this->assertFalse($user->isSchoolAdmin());
    }

    public function test_is_school_admin_returns_false_for_professor(): void
    {
        $user = User::factory()->make(['role' => 'professor', 'school_id' => null]);

        $this->assertFalse($user->isSchoolAdmin());
    }

    public function test_is_school_admin_returns_false_for_aluno(): void
    {
        $user = User::factory()->make(['role' => 'aluno', 'school_id' => null]);

        $this->assertFalse($user->isSchoolAdmin());
    }

    // ── isAdmin() — alias of isSchoolAdmin() since the legacy 'admin' role was retired ──

    public function test_is_admin_returns_true_for_school_admin_role(): void
    {
        $user = User::factory()->make(['role' => 'school_admin', 'school_id' => null]);

        $this->assertTrue($user->isAdmin());
    }

    public function test_is_admin_returns_false_for_super_admin(): void
    {
        $user = User::factory()->make(['role' => 'super_admin', 'school_id' => null]);

        $this->assertFalse($user->isAdmin());
    }

    public function test_is_admin_returns_false_for_professor(): void
    {
        $user = User::factory()->make(['role' => 'professor', 'school_id' => null]);

        $this->assertFalse($user->isAdmin());
    }

    public function test_is_admin_returns_false_for_aluno(): void
    {
        $user = User::factory()->make(['role' => 'aluno', 'school_id' => null]);

        $this->assertFalse($user->isAdmin());
    }

    // ── isProfessor() / isAluno() ───────────────────────────────

    public function test_is_professor_still_works(): void
    {
        $professor = User::factory()->make(['role' => 'professor', 'school_id' => null]);
        $schoolAdmin = User::factory()->make(['role' => 'school_admin', 'school_id' => null]);

        $this->assertTrue($professor->isProfessor());
        $this->assertFalse($schoolAdmin->isProfessor());
    }

    public function test_is_aluno_still_works(): void
    {
        $aluno = User::factory()->make(['role' => 'aluno', 'school_id' => null]);
        $professor = User::factory()->make(['role' => 'professor', 'school_id' => null]);

        $this->assertTrue($aluno->isAluno());
        $this->assertFalse($professor->isAluno());
    }
}
