<?php

namespace App\Http\Controllers;

use App\Actions\Payments\GetRevenueReportAction;
use App\Actions\Payments\RegisterPaymentAction;
use App\Http\Requests\StorePaymentRequest;
use App\Models\LessonPackage;
use App\Models\Payment;
use App\Models\User;
use Inertia\Inertia;

/**
 * Manages payment recording and revenue reporting for lesson packages.
 *
 * Includes manual tenant isolation checks (school_id comparison) since global
 * scopes are not yet active. The store method handles duplicate payment attempts
 * by catching unique constraint violations and showing a user-friendly error.
 *
 * @see RegisterPaymentAction  For payment creation logic
 * @see GetRevenueReportAction For the admin revenue report
 */
class PaymentController extends Controller
{
    /**
     * Show a student's packages with their payment status.
     * Includes a manual tenant isolation check on school_id.
     */
    public function index(User $student)
    {
        if ($student->school_id !== null && $student->school_id !== request()->user()->school_id) {
            abort(403);
        }

        $packages = $student->lessonPackages()
            ->with('payment')
            ->latest()
            ->get()
            ->map(fn ($pkg) => [
                'id'            => $pkg->id,
                'total_lessons' => $pkg->total_lessons,
                'used_lessons'  => $pkg->used_lessons,
                'remaining'     => $pkg->remaining,
                'price'         => $pkg->price,
                'currency'      => $pkg->currency,
                'is_active'     => $pkg->isActive(),
                'is_paid'       => $pkg->isPaid(),
                'purchased_at'  => $pkg->purchased_at?->format('Y-m-d'),
                'expires_at'    => $pkg->expires_at?->format('Y-m-d'),
                'payment'       => $pkg->payment ? [
                    'amount'  => $pkg->payment->amount,
                    'method'  => $pkg->payment->method,
                    'paid_at' => $pkg->payment->paid_at?->format('Y-m-d'),
                ] : null,
            ]);

        return Inertia::render('Payments/Index', [
            'student'  => $student->only('id', 'name', 'email'),
            'packages' => $packages,
        ]);
    }

    /**
     * Register a payment for a student's package. Catches duplicate payment attempts
     * (unique constraint on lesson_package_id) and shows a user-friendly flash error.
     */
    public function store(StorePaymentRequest $request, User $student, LessonPackage $package, RegisterPaymentAction $action)
    {
        if ($student->school_id !== null && $student->school_id !== $request->user()->school_id) {
            abort(403);
        }

        try {
            $action->execute($student, $package, $request->validated(), $request->user()->id);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return back()->with('error', 'Já existe um pagamento registrado para este pacote.');
        } catch (\Illuminate\Database\QueryException $e) {
            // Catch older Laravel versions or SQLite unique violations
            if (str_contains($e->getMessage(), 'UNIQUE constraint failed') || $e->getCode() === '23000') {
                return back()->with('error', 'Já existe um pagamento registrado para este pacote.');
            }
            throw $e;
        }

        return back()->with('success', 'Pagamento registrado com sucesso.');
    }

    /**
     * Admin revenue report: total revenue, monthly breakdown, per-method stats,
     * paid/unpaid counts, and recent payments. Scoped to the admin's school.
     */
    public function report(GetRevenueReportAction $action)
    {
        $data = $action->execute(auth()->user()->school_id);

        return Inertia::render('Admin/PaymentReport', $data);
    }
}
