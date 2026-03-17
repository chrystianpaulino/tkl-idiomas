<?php

namespace App\Http\Controllers;

use App\Actions\Payments\RegisterPaymentAction;
use App\Http\Requests\StorePaymentRequest;
use App\Models\LessonPackage;
use App\Models\User;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function index(User $student)
    {
        // No authorize() needed — route is behind role:admin middleware

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

    public function store(StorePaymentRequest $request, User $student, LessonPackage $package, RegisterPaymentAction $action)
    {
        try {
            $action->execute($student, $package, $request->validated());
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
}
