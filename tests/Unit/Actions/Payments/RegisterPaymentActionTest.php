<?php

namespace Tests\Unit\Actions\Payments;

use App\Actions\Payments\RegisterPaymentAction;
use App\Models\LessonPackage;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterPaymentActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_payment_with_correct_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $student = User::factory()->create(); // default role = aluno
        $package = LessonPackage::factory()->create([
            'student_id'    => $student->id,
            'total_lessons' => 4,
        ]);

        $payment = (new RegisterPaymentAction)->execute($student, $package, [
            'amount'  => '220.00',
            'method'  => 'pix',
            'paid_at' => '2026-03-01 10:00:00',
        ]);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertDatabaseHas('payments', [
            'student_id'        => $student->id,
            'lesson_package_id' => $package->id,
            'registered_by'     => $admin->id,
            'amount'            => '220.00',
            'method'            => 'pix',
            'currency'          => 'BRL',
        ]);
    }

    public function test_throws_403_when_package_does_not_belong_to_student(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $student = User::factory()->create();
        $otherStudent = User::factory()->create();
        $package = LessonPackage::factory()->create(['student_id' => $otherStudent->id]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        (new RegisterPaymentAction)->execute($student, $package, [
            'amount'  => '220.00',
            'method'  => 'pix',
            'paid_at' => now()->toDateTimeString(),
        ]);
    }

    public function test_defaults_currency_to_brl_when_not_provided(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $student = User::factory()->create();
        $package = LessonPackage::factory()->create(['student_id' => $student->id]);

        $payment = (new RegisterPaymentAction)->execute($student, $package, [
            'amount'  => '100.00',
            'method'  => 'cash',
            'paid_at' => now()->toDateTimeString(),
        ]);

        $this->assertEquals('BRL', $payment->currency);
    }

    public function test_registered_by_is_set_from_authenticated_user(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $student = User::factory()->create();
        $package = LessonPackage::factory()->create(['student_id' => $student->id]);

        $payment = (new RegisterPaymentAction)->execute($student, $package, [
            'amount'  => '220.00',
            'method'  => 'pix',
            'paid_at' => now()->toDateTimeString(),
        ]);

        $this->assertEquals($admin->id, $payment->registered_by);
    }
}
