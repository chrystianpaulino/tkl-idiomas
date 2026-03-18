<?php

namespace Tests\Unit\Models;

use App\Models\School;
use App\Models\Scopes\SchoolScope;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    // ── Filtering ───────────────────────────────────────────────

    public function test_scope_filters_by_active_tenant(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        $profA = User::factory()->create(['role' => 'professor', 'school_id' => $schoolA->id]);
        $profB = User::factory()->create(['role' => 'professor', 'school_id' => $schoolB->id]);

        // Create classes without tenant context so scope doesn't interfere
        TurmaClass::withoutGlobalScope(SchoolScope::class)->create([
            'name' => 'Class A',
            'professor_id' => $profA->id,
            'school_id' => $schoolA->id,
        ]);
        TurmaClass::withoutGlobalScope(SchoolScope::class)->create([
            'name' => 'Class B',
            'professor_id' => $profB->id,
            'school_id' => $schoolB->id,
        ]);

        // Bind tenant context for school A
        app()->instance('tenant.school_id', $schoolA->id);

        $this->assertSame(1, TurmaClass::count());
        $this->assertSame('Class A', TurmaClass::first()->name);
    }

    public function test_scope_is_noop_when_no_tenant_bound(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        $profA = User::factory()->create(['role' => 'professor', 'school_id' => $schoolA->id]);
        $profB = User::factory()->create(['role' => 'professor', 'school_id' => $schoolB->id]);

        TurmaClass::withoutGlobalScope(SchoolScope::class)->create([
            'name' => 'Class A',
            'professor_id' => $profA->id,
            'school_id' => $schoolA->id,
        ]);
        TurmaClass::withoutGlobalScope(SchoolScope::class)->create([
            'name' => 'Class B',
            'professor_id' => $profB->id,
            'school_id' => $schoolB->id,
        ]);

        // No tenant binding — scope is a no-op
        $this->assertSame(2, TurmaClass::count());
    }

    // ── Auto-assignment on creating ─────────────────────────────

    public function test_creating_event_auto_assigns_school_id(): void
    {
        $school = School::factory()->create();
        $professor = User::factory()->create(['role' => 'professor', 'school_id' => $school->id]);

        // Bind tenant context
        app()->instance('tenant.school_id', $school->id);

        // Create without explicitly setting school_id
        $turmaClass = TurmaClass::create([
            'name' => 'Auto-assigned Class',
            'professor_id' => $professor->id,
        ]);

        $this->assertSame($school->id, $turmaClass->school_id);
    }

    public function test_explicit_school_id_is_not_overridden_by_context(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();
        $professor = User::factory()->create(['role' => 'professor', 'school_id' => $schoolB->id]);

        // Bind tenant context for school A
        app()->instance('tenant.school_id', $schoolA->id);

        // Create with explicit school_id for school B
        $turmaClass = TurmaClass::withoutGlobalScope(SchoolScope::class)->create([
            'name' => 'Explicit School Class',
            'professor_id' => $professor->id,
            'school_id' => $schoolB->id,
        ]);

        $this->assertSame($schoolB->id, $turmaClass->fresh()->school_id);
    }

    // ── Bypass ──────────────────────────────────────────────────

    public function test_without_global_scope_bypasses_tenant_filter(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        $profA = User::factory()->create(['role' => 'professor', 'school_id' => $schoolA->id]);
        $profB = User::factory()->create(['role' => 'professor', 'school_id' => $schoolB->id]);

        TurmaClass::withoutGlobalScope(SchoolScope::class)->create([
            'name' => 'Class A',
            'professor_id' => $profA->id,
            'school_id' => $schoolA->id,
        ]);
        TurmaClass::withoutGlobalScope(SchoolScope::class)->create([
            'name' => 'Class B',
            'professor_id' => $profB->id,
            'school_id' => $schoolB->id,
        ]);

        // Bind tenant for school A
        app()->instance('tenant.school_id', $schoolA->id);

        // Scoped query: 1 result
        $this->assertSame(1, TurmaClass::count());

        // Bypassed query: 2 results
        $this->assertSame(2, TurmaClass::withoutGlobalScope(SchoolScope::class)->count());
    }

    // ── Smoke test: scope applies across BelongsToSchool models ─

    public function test_scope_applies_to_all_belongstoschool_models(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        $profA = User::factory()->create(['role' => 'professor', 'school_id' => $schoolA->id]);
        $profB = User::factory()->create(['role' => 'professor', 'school_id' => $schoolB->id]);

        // Create one class in each school
        TurmaClass::withoutGlobalScope(SchoolScope::class)->create([
            'name' => 'Class A',
            'professor_id' => $profA->id,
            'school_id' => $schoolA->id,
        ]);
        TurmaClass::withoutGlobalScope(SchoolScope::class)->create([
            'name' => 'Class B',
            'professor_id' => $profB->id,
            'school_id' => $schoolB->id,
        ]);

        // Bind tenant for school A
        app()->instance('tenant.school_id', $schoolA->id);

        // TurmaClass uses BelongsToSchool — should be filtered
        $this->assertSame(1, TurmaClass::count());

        // Without binding, both visible
        app()->forgetInstance('tenant.school_id');
        $this->assertSame(2, TurmaClass::count());
    }
}
