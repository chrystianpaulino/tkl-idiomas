<?php

namespace Tests\Unit\Actions\Payments;

use App\Actions\Payments\RegisterPaymentAction;
use App\Models\LessonPackage;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RegisterPaymentActionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private RegisterPaymentAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->schoolAdmin()->create();
        $this->actingAs($this->admin);
        $this->action = new RegisterPaymentAction;
    }

    public function test_creates_payment_with_correct_fields(): void
    {
        $student = User::factory()->create(); // default role = aluno
        $package = LessonPackage::factory()->create([
            'student_id' => $student->id,
            'total_lessons' => 4,
        ]);

        $payment = $this->action->execute($student, $package, [
            'amount' => '220.00',
            'method' => 'pix',
            'paid_at' => '2026-03-01 10:00:00',
        ], $this->admin->id);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertDatabaseHas('payments', [
            'student_id' => $student->id,
            'lesson_package_id' => $package->id,
            'registered_by' => $this->admin->id,
            'amount' => '220.00',
            'method' => 'pix',
            'currency' => 'BRL',
        ]);
    }

    public function test_throws_403_when_package_does_not_belong_to_student(): void
    {
        $student = User::factory()->create();
        $otherStudent = User::factory()->create();
        $package = LessonPackage::factory()->create(['student_id' => $otherStudent->id]);

        try {
            $this->action->execute($student, $package, [
                'amount' => '220.00',
                'method' => 'pix',
                'paid_at' => now()->toDateTimeString(),
            ], $this->admin->id);

            $this->fail('Expected HttpException was not thrown.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_defaults_currency_to_brl_when_not_provided(): void
    {
        $student = User::factory()->create();
        $package = LessonPackage::factory()->create(['student_id' => $student->id]);

        $payment = $this->action->execute($student, $package, [
            'amount' => '100.00',
            'method' => 'cash',
            'paid_at' => now()->toDateTimeString(),
        ], $this->admin->id);

        $this->assertEquals('BRL', $payment->currency);
    }

    public function test_registered_by_is_set_from_explicit_argument(): void
    {
        $student = User::factory()->create();
        $package = LessonPackage::factory()->create(['student_id' => $student->id]);

        $payment = $this->action->execute($student, $package, [
            'amount' => '220.00',
            'method' => 'pix',
            'paid_at' => now()->toDateTimeString(),
        ], $this->admin->id);

        $this->assertEquals($this->admin->id, $payment->registered_by);
    }

    public function test_throws_exception_when_amount_is_zero(): void
    {
        $student = User::factory()->create();
        $package = LessonPackage::factory()->create(['student_id' => $student->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment amount must be greater than zero.');

        $this->action->execute($student, $package, [
            'amount' => 0,
            'method' => 'pix',
            'paid_at' => now()->toDateTimeString(),
        ], $this->admin->id);
    }

    public function test_throws_exception_when_amount_is_negative(): void
    {
        $student = User::factory()->create();
        $package = LessonPackage::factory()->create(['student_id' => $student->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment amount must be greater than zero.');

        $this->action->execute($student, $package, [
            'amount' => -50,
            'method' => 'pix',
            'paid_at' => now()->toDateTimeString(),
        ], $this->admin->id);
    }

    public function test_throws_unique_constraint_when_paying_same_package_twice(): void
    {
        $student = User::factory()->create();
        $package = LessonPackage::factory()->create(['student_id' => $student->id]);

        $paymentData = [
            'amount' => '220.00',
            'method' => 'pix',
            'paid_at' => now()->toDateTimeString(),
        ];

        // First payment succeeds
        $this->action->execute($student, $package, $paymentData, $this->admin->id);

        // Second payment for the same package triggers unique constraint violation
        $this->expectException(UniqueConstraintViolationException::class);

        $this->action->execute($student, $package, $paymentData, $this->admin->id);
    }
}
