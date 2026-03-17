<?php

namespace Tests\Unit\Models;

use App\Models\LessonPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonPackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_active_includes_null_expiry_package(): void
    {
        $student = User::factory()->create(['role' => 'aluno']);
        $package = LessonPackage::factory()->create([
            'student_id' => $student->id,
            'total_lessons' => 10,
            'used_lessons' => 3,
            'expires_at' => null,
        ]);

        $result = LessonPackage::active()->get();

        $this->assertCount(1, $result);
        $this->assertEquals($package->id, $result->first()->id);
    }

    public function test_scope_active_excludes_expired_package(): void
    {
        $student = User::factory()->create(['role' => 'aluno']);
        LessonPackage::factory()->expired()->create([
            'student_id' => $student->id,
            'total_lessons' => 10,
            'used_lessons' => 3,
        ]);

        $this->assertCount(0, LessonPackage::active()->get());
    }

    public function test_scope_active_excludes_exhausted_package(): void
    {
        $student = User::factory()->create(['role' => 'aluno']);
        LessonPackage::factory()->exhausted()->create([
            'student_id' => $student->id,
            'total_lessons' => 10,
        ]);

        $this->assertCount(0, LessonPackage::active()->get());
    }

    public function test_scope_active_includes_future_expiry_package(): void
    {
        $student = User::factory()->create(['role' => 'aluno']);
        LessonPackage::factory()->create([
            'student_id' => $student->id,
            'total_lessons' => 10,
            'used_lessons' => 0,
            'expires_at' => now()->addMonth(),
        ]);

        $this->assertCount(1, LessonPackage::active()->get());
    }

    public function test_remaining_attribute_returns_correct_value(): void
    {
        $package = LessonPackage::factory()->make([
            'total_lessons' => 10,
            'used_lessons' => 3,
        ]);

        $this->assertEquals(7, $package->remaining);
    }

    public function test_remaining_attribute_never_returns_negative(): void
    {
        $package = LessonPackage::factory()->make([
            'total_lessons' => 5,
            'used_lessons' => 12,
        ]);

        $this->assertEquals(0, $package->remaining);
    }

    public function test_is_exhausted_when_used_equals_total(): void
    {
        $package = LessonPackage::factory()->make([
            'total_lessons' => 10,
            'used_lessons' => 10,
        ]);

        $this->assertTrue($package->isExhausted());
        $this->assertFalse($package->isActive());
    }

    public function test_is_not_expired_when_expires_at_is_null(): void
    {
        $package = LessonPackage::factory()->make(['expires_at' => null]);

        $this->assertFalse($package->isExpired());
    }

    public function test_is_expired_when_expires_at_is_in_past(): void
    {
        $package = LessonPackage::factory()->expired()->make();

        $this->assertTrue($package->isExpired());
        $this->assertFalse($package->isActive());
    }

    public function test_is_active_consistent_with_scope_active(): void
    {
        $student = User::factory()->create(['role' => 'aluno']);

        $active = LessonPackage::factory()->create([
            'student_id' => $student->id,
            'total_lessons' => 10,
            'used_lessons' => 3,
            'expires_at' => null,
        ]);
        $expired = LessonPackage::factory()->expired()->create([
            'student_id' => $student->id,
            'total_lessons' => 10,
            'used_lessons' => 3,
        ]);
        $exhausted = LessonPackage::factory()->exhausted()->create([
            'student_id' => $student->id,
            'total_lessons' => 10,
        ]);

        $this->assertTrue($active->isActive());
        $this->assertFalse($expired->isActive());
        $this->assertFalse($exhausted->isActive());

        $activeIds = LessonPackage::active()->pluck('id');
        $this->assertTrue($activeIds->contains($active->id));
        $this->assertFalse($activeIds->contains($expired->id));
        $this->assertFalse($activeIds->contains($exhausted->id));
    }
}
