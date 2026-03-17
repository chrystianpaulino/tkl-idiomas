<?php

namespace App\Actions\Payments;

use App\Models\LessonPackage;
use App\Models\Payment;
use App\Models\User;

class GetRevenueReportAction
{
    public function execute(?int $schoolId = null): array
    {
        $paymentQuery = Payment::query();
        $packageQuery = LessonPackage::query();
        $studentQuery = User::where('role', 'aluno');

        if ($schoolId !== null) {
            $paymentQuery->where('school_id', $schoolId);
            $packageQuery->where('school_id', $schoolId);
            $studentQuery->where('school_id', $schoolId);
        }

        $totalRevenue = (clone $paymentQuery)->sum('amount');

        // SQLite-compatible date formatting with strftime
        $revenueByMonth = (clone $paymentQuery)
            ->selectRaw("strftime('%m/%Y', paid_at) as month, SUM(amount) as total")
            ->whereNotNull('paid_at')
            ->groupByRaw("strftime('%m/%Y', paid_at)")
            ->orderByRaw("MIN(paid_at) ASC")
            ->get()
            ->map(fn ($row) => [
                'month' => $row->month,
                'total' => (float) $row->total,
            ])
            ->values()
            ->toArray();

        $byMethod = (clone $paymentQuery)
            ->selectRaw('method, SUM(amount) as total, COUNT(*) as count')
            ->whereNotNull('amount')
            ->groupBy('method')
            ->get()
            ->map(fn ($row) => [
                'method' => $row->method,
                'total'  => (float) $row->total,
                'count'  => (int) $row->count,
            ])
            ->values()
            ->toArray();

        $paidPackageIds = (clone $paymentQuery)->pluck('lesson_package_id');

        $paidPackagesCount = $paidPackageIds->count();

        $unpaidPackagesCount = (clone $packageQuery)
            ->whereNotIn('id', $paidPackageIds)
            ->count();

        $totalStudents = (clone $studentQuery)->count();

        $recentPayments = (clone $paymentQuery)
            ->with('student:id,name')
            ->latest('paid_at')
            ->limit(10)
            ->get()
            ->map(fn (Payment $payment) => [
                'id'           => $payment->id,
                'student_name' => $payment->student?->name ?? 'N/A',
                'amount'       => (float) $payment->amount,
                'method'       => $payment->method,
                'paid_at'      => $payment->paid_at?->format('Y-m-d'),
            ])
            ->toArray();

        return [
            'total_revenue'       => (float) $totalRevenue,
            'revenue_by_month'    => $revenueByMonth,
            'by_method'           => $byMethod,
            'paid_packages_count' => $paidPackagesCount,
            'unpaid_packages_count' => $unpaidPackagesCount,
            'total_students'      => $totalStudents,
            'recent_payments'     => $recentPayments,
        ];
    }
}
