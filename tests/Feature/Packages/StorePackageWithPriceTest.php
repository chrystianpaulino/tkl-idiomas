<?php

namespace Tests\Feature\Packages;

use App\Models\LessonPackage;
use App\Models\School;
use App\Models\Scopes\SchoolScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * HTTP-level tests for POST /admin/users/{student}/packages.
 *
 * Verifies the StorePackageRequest validation contract for the new
 * price and currency fields, plus end-to-end persistence through the
 * controller -> CreatePackageAction -> Eloquent path.
 */
class StorePackageWithPriceTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private User $admin;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create();
        $this->admin = User::factory()->create([
            'role' => 'school_admin',
            'school_id' => $this->school->id,
        ]);
        $this->student = User::factory()->create([
            'role' => 'aluno',
            'school_id' => $this->school->id,
        ]);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    #[Test]
    public function school_admin_creates_package_with_price_and_currency(): void
    {
        $this->actingAs($this->admin)
            ->post("/admin/users/{$this->student->id}/packages", [
                'total_lessons' => 4,
                'price' => 220.00,
                'currency' => 'BRL',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $package = LessonPackage::withoutGlobalScope(SchoolScope::class)
            ->where('student_id', $this->student->id)
            ->first();

        $this->assertNotNull($package);
        $this->assertSame(4, $package->total_lessons);
        $this->assertSame('220.00', $package->price);
        $this->assertSame('BRL', $package->currency);
        $this->assertSame($this->school->id, $package->school_id);
    }

    #[Test]
    public function request_validation_rejects_missing_price(): void
    {
        $this->actingAs($this->admin)
            ->post("/admin/users/{$this->student->id}/packages", [
                'total_lessons' => 4,
                'currency' => 'BRL',
            ])
            ->assertSessionHasErrors(['price']);

        $this->assertDatabaseCount('lesson_packages', 0);
    }

    #[Test]
    public function request_validation_rejects_missing_currency(): void
    {
        $this->actingAs($this->admin)
            ->post("/admin/users/{$this->student->id}/packages", [
                'total_lessons' => 4,
                'price' => 220.00,
            ])
            ->assertSessionHasErrors(['currency']);

        $this->assertDatabaseCount('lesson_packages', 0);
    }

    #[Test]
    public function request_validation_rejects_zero_price(): void
    {
        $this->actingAs($this->admin)
            ->post("/admin/users/{$this->student->id}/packages", [
                'total_lessons' => 4,
                'price' => 0,
                'currency' => 'BRL',
            ])
            ->assertSessionHasErrors(['price']);

        $this->assertDatabaseCount('lesson_packages', 0);
    }

    #[Test]
    public function request_validation_rejects_negative_price(): void
    {
        $this->actingAs($this->admin)
            ->post("/admin/users/{$this->student->id}/packages", [
                'total_lessons' => 4,
                'price' => -10.00,
                'currency' => 'BRL',
            ])
            ->assertSessionHasErrors(['price']);

        $this->assertDatabaseCount('lesson_packages', 0);
    }

    #[Test]
    public function request_validation_rejects_price_above_decimal_max(): void
    {
        $this->actingAs($this->admin)
            ->post("/admin/users/{$this->student->id}/packages", [
                'total_lessons' => 4,
                'price' => 9999999.99, // exceeds decimal(8,2) max of 999999.99
                'currency' => 'BRL',
            ])
            ->assertSessionHasErrors(['price']);

        $this->assertDatabaseCount('lesson_packages', 0);
    }

    #[Test]
    public function request_validation_rejects_invalid_currency_code(): void
    {
        $this->actingAs($this->admin)
            ->post("/admin/users/{$this->student->id}/packages", [
                'total_lessons' => 4,
                'price' => 220.00,
                'currency' => 'BRX',
            ])
            ->assertSessionHasErrors(['currency']);

        $this->assertDatabaseCount('lesson_packages', 0);
    }

    #[Test]
    public function request_validation_rejects_lowercase_currency_code(): void
    {
        $this->actingAs($this->admin)
            ->post("/admin/users/{$this->student->id}/packages", [
                'total_lessons' => 4,
                'price' => 220.00,
                'currency' => 'brl',
            ])
            ->assertSessionHasErrors(['currency']);

        $this->assertDatabaseCount('lesson_packages', 0);
    }

    #[Test]
    public function request_validation_rejects_currency_with_wrong_length(): void
    {
        $this->actingAs($this->admin)
            ->post("/admin/users/{$this->student->id}/packages", [
                'total_lessons' => 4,
                'price' => 220.00,
                'currency' => 'BR',
            ])
            ->assertSessionHasErrors(['currency']);

        $this->assertDatabaseCount('lesson_packages', 0);
    }

    #[Test]
    public function school_admin_can_create_package_with_usd_currency(): void
    {
        $this->actingAs($this->admin)
            ->post("/admin/users/{$this->student->id}/packages", [
                'total_lessons' => 4,
                'price' => 50.00,
                'currency' => 'USD',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $package = LessonPackage::withoutGlobalScope(SchoolScope::class)
            ->where('student_id', $this->student->id)
            ->first();

        $this->assertSame('USD', $package->currency);
        $this->assertSame('50.00', $package->price);
    }

    #[Test]
    public function non_admin_cannot_create_package(): void
    {
        $professor = User::factory()->create([
            'role' => 'professor',
            'school_id' => $this->school->id,
        ]);

        $this->actingAs($professor)
            ->post("/admin/users/{$this->student->id}/packages", [
                'total_lessons' => 4,
                'price' => 220.00,
                'currency' => 'BRL',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('lesson_packages', 0);
    }
}
