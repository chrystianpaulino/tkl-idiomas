<?php

namespace Tests\Unit\Actions\Payments;

use App\Actions\Payments\GetRevenueReportAction;
use App\Models\LessonPackage;
use App\Models\Payment;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetRevenueReportActionTest extends TestCase
{
    use RefreshDatabase;

    private GetRevenueReportAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new GetRevenueReportAction;
    }

    // --- Return shape ---

    public function test_returns_expected_keys(): void
    {
        $result = $this->action->execute();

        $this->assertArrayHasKey('total_revenue', $result);
        $this->assertArrayHasKey('revenue_by_month', $result);
        $this->assertArrayHasKey('by_method', $result);
        $this->assertArrayHasKey('paid_packages_count', $result);
        $this->assertArrayHasKey('unpaid_packages_count', $result);
        $this->assertArrayHasKey('total_students', $result);
        $this->assertArrayHasKey('recent_payments', $result);
    }

    public function test_returns_zero_revenue_when_no_payments_exist(): void
    {
        $result = $this->action->execute();

        $this->assertSame('0.00', $result['total_revenue']);
        $this->assertSame([], $result['revenue_by_month']);
        $this->assertSame([], $result['by_method']);
        $this->assertSame([], $result['recent_payments']);
        $this->assertSame(0, $result['paid_packages_count']);
        $this->assertSame(0, $result['unpaid_packages_count']);
        $this->assertSame(0, $result['total_students']);
    }

    // --- total_revenue ---

    public function test_total_revenue_sums_all_payments(): void
    {
        $student = User::factory()->create(['role' => 'aluno']);

        $pkg1 = LessonPackage::factory()->create(['student_id' => $student->id]);
        $pkg2 = LessonPackage::factory()->create(['student_id' => $student->id]);

        $admin = User::factory()->create(['role' => 'admin']);
        Payment::factory()->create(['student_id' => $student->id, 'lesson_package_id' => $pkg1->id, 'registered_by' => $admin->id, 'amount' => 200.00, 'school_id' => $student->school_id]);
        Payment::factory()->create(['student_id' => $student->id, 'lesson_package_id' => $pkg2->id, 'registered_by' => $admin->id, 'amount' => 300.00, 'school_id' => $student->school_id]);

        $result = $this->action->execute();

        $this->assertSame('500.00', $result['total_revenue']);
    }

    // --- total_students ---

    public function test_total_students_counts_only_aluno_role(): void
    {
        User::factory()->create(['role' => 'aluno']);
        User::factory()->create(['role' => 'aluno']);
        User::factory()->create(['role' => 'professor']);
        User::factory()->create(['role' => 'admin']);

        $result = $this->action->execute();

        $this->assertSame(2, $result['total_students']);
    }

    // --- paid_packages_count / unpaid_packages_count ---

    public function test_counts_paid_and_unpaid_packages(): void
    {
        $student = User::factory()->create(['role' => 'aluno']);
        $admin = User::factory()->create(['role' => 'admin']);

        // Paid package
        $paidPkg = LessonPackage::factory()->create(['student_id' => $student->id]);
        Payment::factory()->create(['student_id' => $student->id, 'lesson_package_id' => $paidPkg->id, 'registered_by' => $admin->id, 'school_id' => $student->school_id]);

        // Unpaid packages (no payment)
        LessonPackage::factory()->count(2)->create(['student_id' => $student->id]);

        $result = $this->action->execute();

        $this->assertSame(1, $result['paid_packages_count']);
        $this->assertSame(2, $result['unpaid_packages_count']);
    }

    public function test_unpaid_count_is_zero_when_all_packages_are_paid(): void
    {
        $student = User::factory()->create(['role' => 'aluno']);
        $admin = User::factory()->create(['role' => 'admin']);

        $pkg = LessonPackage::factory()->create(['student_id' => $student->id]);
        Payment::factory()->create(['student_id' => $student->id, 'lesson_package_id' => $pkg->id, 'registered_by' => $admin->id, 'school_id' => $student->school_id]);

        $result = $this->action->execute();

        $this->assertSame(0, $result['unpaid_packages_count']);
    }

    // --- by_method ---

    public function test_by_method_groups_by_payment_method(): void
    {
        $student = User::factory()->create(['role' => 'aluno']);
        $admin = User::factory()->create(['role' => 'admin']);

        $pkg1 = LessonPackage::factory()->create(['student_id' => $student->id]);
        $pkg2 = LessonPackage::factory()->create(['student_id' => $student->id]);
        $pkg3 = LessonPackage::factory()->create(['student_id' => $student->id]);

        Payment::factory()->create(['student_id' => $student->id, 'lesson_package_id' => $pkg1->id, 'registered_by' => $admin->id, 'method' => 'pix', 'amount' => 100.00, 'school_id' => $student->school_id]);
        Payment::factory()->create(['student_id' => $student->id, 'lesson_package_id' => $pkg2->id, 'registered_by' => $admin->id, 'method' => 'pix', 'amount' => 200.00, 'school_id' => $student->school_id]);
        Payment::factory()->create(['student_id' => $student->id, 'lesson_package_id' => $pkg3->id, 'registered_by' => $admin->id, 'method' => 'cash', 'amount' => 50.00, 'school_id' => $student->school_id]);

        $result = $this->action->execute();

        $byMethod = collect($result['by_method'])->keyBy('method');

        $this->assertArrayHasKey('pix', $byMethod);
        $this->assertArrayHasKey('cash', $byMethod);
        $this->assertSame('300.00', $byMethod['pix']['total']);
        $this->assertSame(2, $byMethod['pix']['count']);
        $this->assertSame('50.00', $byMethod['cash']['total']);
        $this->assertSame(1, $byMethod['cash']['count']);
    }

    // --- recent_payments ---

    public function test_recent_payments_includes_student_name(): void
    {
        $student = User::factory()->create(['role' => 'aluno', 'name' => 'João Teste']);
        $admin = User::factory()->create(['role' => 'admin']);

        $pkg = LessonPackage::factory()->create(['student_id' => $student->id]);
        Payment::factory()->create(['student_id' => $student->id, 'lesson_package_id' => $pkg->id, 'registered_by' => $admin->id, 'school_id' => $student->school_id]);

        $result = $this->action->execute();

        $this->assertCount(1, $result['recent_payments']);
        $this->assertSame('João Teste', $result['recent_payments'][0]['student_name']);
    }

    public function test_recent_payments_limited_to_10(): void
    {
        $student = User::factory()->create(['role' => 'aluno']);
        $admin = User::factory()->create(['role' => 'admin']);

        for ($i = 0; $i < 15; $i++) {
            $pkg = LessonPackage::factory()->create(['student_id' => $student->id]);
            Payment::factory()->create([
                'student_id' => $student->id,
                'lesson_package_id' => $pkg->id,
                'registered_by' => $admin->id,
                'school_id' => $student->school_id,
            ]);
        }

        $result = $this->action->execute();

        $this->assertCount(10, $result['recent_payments']);
    }

    public function test_recent_payments_entry_has_expected_keys(): void
    {
        $student = User::factory()->create(['role' => 'aluno']);
        $admin = User::factory()->create(['role' => 'admin']);

        $pkg = LessonPackage::factory()->create(['student_id' => $student->id]);
        Payment::factory()->create(['student_id' => $student->id, 'lesson_package_id' => $pkg->id, 'registered_by' => $admin->id, 'school_id' => $student->school_id]);

        $result = $this->action->execute();

        $entry = $result['recent_payments'][0];
        $this->assertArrayHasKey('id', $entry);
        $this->assertArrayHasKey('student_name', $entry);
        $this->assertArrayHasKey('amount', $entry);
        $this->assertArrayHasKey('method', $entry);
        $this->assertArrayHasKey('paid_at', $entry);
    }

    public function test_recent_payments_are_ordered_by_most_recent_first(): void
    {
        $student = User::factory()->create(['role' => 'aluno']);
        $admin = User::factory()->create(['role' => 'admin']);

        $pkgOld = LessonPackage::factory()->create(['student_id' => $student->id]);
        $pkgNew = LessonPackage::factory()->create(['student_id' => $student->id]);

        Payment::factory()->create([
            'student_id' => $student->id,
            'lesson_package_id' => $pkgOld->id,
            'registered_by' => $admin->id,
            'paid_at' => '2026-01-10',
            'school_id' => $student->school_id,
        ]);
        Payment::factory()->create([
            'student_id' => $student->id,
            'lesson_package_id' => $pkgNew->id,
            'registered_by' => $admin->id,
            'paid_at' => '2026-03-10',
            'school_id' => $student->school_id,
        ]);

        $result = $this->action->execute();

        $this->assertEquals('2026-03-10', $result['recent_payments'][0]['paid_at']);
        $this->assertEquals('2026-01-10', $result['recent_payments'][1]['paid_at']);
    }

    // --- school_id scoping ---

    public function test_filters_by_school_id_when_provided(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        $studentA = User::factory()->create(['role' => 'aluno', 'school_id' => $schoolA->id]);
        $studentB = User::factory()->create(['role' => 'aluno', 'school_id' => $schoolB->id]);

        $admin = User::factory()->create(['role' => 'admin']);

        $pkgA = LessonPackage::factory()->create(['student_id' => $studentA->id, 'school_id' => $schoolA->id]);
        $pkgB = LessonPackage::factory()->create(['student_id' => $studentB->id, 'school_id' => $schoolB->id]);

        // Unpaid package in school A
        LessonPackage::factory()->create(['student_id' => $studentA->id, 'school_id' => $schoolA->id]);
        // Unpaid package in school B
        LessonPackage::factory()->create(['student_id' => $studentB->id, 'school_id' => $schoolB->id]);

        Payment::factory()->create(['student_id' => $studentA->id, 'lesson_package_id' => $pkgA->id, 'registered_by' => $admin->id, 'amount' => 100.00, 'school_id' => $schoolA->id]);
        Payment::factory()->create(['student_id' => $studentB->id, 'lesson_package_id' => $pkgB->id, 'registered_by' => $admin->id, 'amount' => 999.00, 'school_id' => $schoolB->id]);

        $result = $this->action->execute($schoolA->id);

        $this->assertSame('100.00', $result['total_revenue']);
        $this->assertSame(1, $result['total_students']);
        $this->assertSame(1, $result['paid_packages_count']);
        $this->assertSame(1, $result['unpaid_packages_count']);
    }

    public function test_without_school_filter_returns_all_data(): void
    {
        $schoolA = School::factory()->create();
        $schoolB = School::factory()->create();

        $studentA = User::factory()->create(['role' => 'aluno', 'school_id' => $schoolA->id]);
        $studentB = User::factory()->create(['role' => 'aluno', 'school_id' => $schoolB->id]);

        $admin = User::factory()->create(['role' => 'admin']);

        $pkgA = LessonPackage::factory()->create(['student_id' => $studentA->id, 'school_id' => $schoolA->id]);
        $pkgB = LessonPackage::factory()->create(['student_id' => $studentB->id, 'school_id' => $schoolB->id]);

        Payment::factory()->create(['student_id' => $studentA->id, 'lesson_package_id' => $pkgA->id, 'registered_by' => $admin->id, 'amount' => 100.00, 'school_id' => $schoolA->id]);
        Payment::factory()->create(['student_id' => $studentB->id, 'lesson_package_id' => $pkgB->id, 'registered_by' => $admin->id, 'amount' => 200.00, 'school_id' => $schoolB->id]);

        $result = $this->action->execute();

        $this->assertSame('300.00', $result['total_revenue']);
        $this->assertSame(2, $result['total_students']);
    }

    // --- revenue_by_month ---

    public function test_revenue_by_month_groups_correctly(): void
    {
        $student = User::factory()->create(['role' => 'aluno']);
        $admin = User::factory()->create(['role' => 'admin']);

        $pkg1 = LessonPackage::factory()->create(['student_id' => $student->id]);
        $pkg2 = LessonPackage::factory()->create(['student_id' => $student->id]);
        $pkg3 = LessonPackage::factory()->create(['student_id' => $student->id]);

        Payment::factory()->create(['student_id' => $student->id, 'lesson_package_id' => $pkg1->id, 'registered_by' => $admin->id, 'paid_at' => '2026-01-15', 'amount' => 100.00, 'school_id' => $student->school_id]);
        Payment::factory()->create(['student_id' => $student->id, 'lesson_package_id' => $pkg2->id, 'registered_by' => $admin->id, 'paid_at' => '2026-01-20', 'amount' => 200.00, 'school_id' => $student->school_id]);
        Payment::factory()->create(['student_id' => $student->id, 'lesson_package_id' => $pkg3->id, 'registered_by' => $admin->id, 'paid_at' => '2026-02-05', 'amount' => 50.00, 'school_id' => $student->school_id]);

        $result = $this->action->execute();

        $byMonth = collect($result['revenue_by_month'])->keyBy('month');

        $this->assertArrayHasKey('01/2026', $byMonth);
        $this->assertArrayHasKey('02/2026', $byMonth);
        $this->assertSame('300.00', $byMonth['01/2026']['total']);
        $this->assertSame('50.00', $byMonth['02/2026']['total']);
    }
}
