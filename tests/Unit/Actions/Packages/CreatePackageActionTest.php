<?php

namespace Tests\Unit\Actions\Packages;

use App\Actions\Packages\CreatePackageAction;
use App\Models\LessonPackage;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for CreatePackageAction focusing on price/currency capture.
 *
 * The action is the single write path for new packages and must persist
 * the agreed sale price exactly as it was validated. Payment.amount is
 * intentionally independent (admins can record discounts/partials), so
 * these tests assert the pricing contract at package-creation time.
 */
class CreatePackageActionTest extends TestCase
{
    use RefreshDatabase;

    private CreatePackageAction $action;

    private School $school;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new CreatePackageAction;
        $this->school = School::factory()->create();
        $this->student = User::factory()->create([
            'role' => 'aluno',
            'school_id' => $this->school->id,
        ]);

        app()->instance('tenant.school_id', $this->school->id);
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant.school_id');
        parent::tearDown();
    }

    public function test_creates_package_with_price_and_currency(): void
    {
        $package = $this->action->execute($this->student, [
            'total_lessons' => 4,
            'price' => 220.00,
            'currency' => 'BRL',
            'expires_at' => null,
        ]);

        // refresh() loads DB defaults (used_lessons starts at 0 via DB default,
        // not via $fillable -- the column is intentionally non-mass-assignable).
        $package->refresh();

        $this->assertInstanceOf(LessonPackage::class, $package);
        $this->assertSame($this->student->id, $package->student_id);
        $this->assertSame(4, $package->total_lessons);
        $this->assertSame('220.00', $package->price);
        $this->assertSame('BRL', $package->currency);
        $this->assertSame(0, $package->used_lessons);
        $this->assertSame($this->school->id, $package->school_id);
    }

    public function test_price_is_persisted_as_decimal_with_two_places(): void
    {
        $package = $this->action->execute($this->student, [
            'total_lessons' => 10,
            'price' => 199.9,
            'currency' => 'BRL',
        ]);

        $package->refresh();

        // decimal:2 cast normalizes to two fractional digits
        $this->assertSame('199.90', $package->price);
    }

    public function test_action_persists_currency_received_from_caller(): void
    {
        // The action is a pure adapter -- it must NOT silently coerce or
        // default the currency. Validation lives in StorePackageRequest.
        $package = $this->action->execute($this->student, [
            'total_lessons' => 4,
            'price' => 50.00,
            'currency' => 'USD',
        ]);

        $this->assertSame('USD', $package->refresh()->currency);
    }

    public function test_creates_package_without_expiration_when_omitted(): void
    {
        $package = $this->action->execute($this->student, [
            'total_lessons' => 4,
            'price' => 220.00,
            'currency' => 'BRL',
        ]);

        $this->assertNull($package->expires_at);
    }

    public function test_assigns_school_id_from_student_for_super_admin_context(): void
    {
        // Reproduce the super_admin path: no tenant context bound, action
        // must still scope the package to the student's school.
        app()->forgetInstance('tenant.school_id');

        $package = $this->action->execute($this->student, [
            'total_lessons' => 4,
            'price' => 220.00,
            'currency' => 'BRL',
        ]);

        $this->assertSame($this->school->id, $package->school_id);
    }
}
