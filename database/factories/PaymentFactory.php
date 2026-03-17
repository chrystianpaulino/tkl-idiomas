<?php

namespace Database\Factories;

use App\Models\LessonPackage;
use App\Models\Payment;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'student_id'        => User::factory()->state(['role' => 'aluno']),
            'lesson_package_id' => LessonPackage::factory(),
            'registered_by'     => User::factory()->admin(),
            'amount'            => $this->faker->randomFloat(2, 50, 2000),
            'currency'          => 'BRL',
            'method'            => $this->faker->randomElement(['pix', 'cash', 'card', 'transfer', 'other']),
            'paid_at'           => $this->faker->dateTimeBetween('-3 months', 'now'),
            'notes'             => $this->faker->optional(0.3)->sentence(),
            'school_id'         => $school,
        ];
    }

    /**
     * Configure the factory to link the student, package, and school consistently.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Payment $payment) {
            // Ensure the package belongs to the same student and school
            // This is handled by explicit state when using the factory
        });
    }

    /**
     * Create a payment with all relationships properly linked.
     */
    public function forStudent(User $student): static
    {
        return $this->state(function () use ($student) {
            $package = LessonPackage::factory()->create([
                'student_id' => $student->id,
                'school_id'  => $student->school_id,
            ]);

            return [
                'student_id'        => $student->id,
                'lesson_package_id' => $package->id,
                'school_id'         => $student->school_id,
            ];
        });
    }
}
