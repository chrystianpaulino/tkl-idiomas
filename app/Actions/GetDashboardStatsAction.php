<?php

namespace App\Actions;

use App\Models\Lesson;
use App\Models\LessonPackage;
use App\Models\Payment;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GetDashboardStatsAction
{
    public function execute(User $user): array
    {
        return match ($user->role) {
            'admin' => $this->adminStats(),
            'professor' => $this->professorStats($user),
            'aluno' => $this->alunoStats($user),
            default => [],
        };
    }

    private function adminStats(): array
    {
        return [
            'total_students' => User::where('role', 'aluno')->count(),
            'total_professors' => User::where('role', 'professor')->count(),
            'total_classes' => TurmaClass::count(),
            'total_lessons' => Lesson::count(),
            'active_packages' => LessonPackage::active()->count(),
            'payment_summary' => [
                'total_revenue' => (float) Payment::sum('amount'),
                'unpaid_count'  => LessonPackage::whereDoesntHave('payment')->count(),
            ],
        ];
    }

    private function professorStats(User $user): array
    {
        $studentsNeedingPackage = LessonPackage::needingPayment()
            ->whereHas('student', function ($q) use ($user) {
                $q->whereHas('enrolledClasses', function ($q2) use ($user) {
                    $q2->where('professor_id', $user->id);
                });
            })
            ->with('student:id,name')
            ->get()
            ->map(fn ($pkg) => [
                'student_id'    => $pkg->student_id,
                'student_name'  => $pkg->student->name,
                'package_id'    => $pkg->id,
                'used_lessons'  => $pkg->used_lessons,
                'total_lessons' => $pkg->total_lessons,
            ]);

        $classPaymentStats = $user->taughtClasses()
            ->with(['students' => function ($q) {
                $q->with(['lessonPackages' => function ($q2) {
                    $q2->active()->with('payment');
                }]);
            }])
            ->get()
            ->map(function ($class) {
                $paid = 0;
                $total = $class->students->count();
                foreach ($class->students as $student) {
                    $hasActivePaid = $student->lessonPackages->contains(fn ($pkg) => $pkg->payment !== null);
                    if ($hasActivePaid) {
                        $paid++;
                    }
                }
                return [
                    'class_id'   => $class->id,
                    'class_name' => $class->name,
                    'paid'       => $paid,
                    'total'      => $total,
                ];
            })
            ->toArray();

        return [
            'total_classes' => $user->taughtClasses()->count(),
            'total_lessons_taught' => Lesson::where('professor_id', $user->id)->count(),
            'recent_lessons' => Lesson::where('professor_id', $user->id)
                ->with(['student', 'turmaClass'])
                ->latest('conducted_at')
                ->limit(5)
                ->get(),
            'studentsNeedingPackage' => $studentsNeedingPackage,
            'class_payment_stats' => $classPaymentStats,
        ];
    }

    private function alunoStats(User $user): array
    {
        $activePackage = $user->lessonPackages()
            ->with('payment')
            ->active()
            ->orderBy('expires_at')
            ->first();

        $recentLessons = $user->lessons()
            ->with('professor:id,name', 'turmaClass:id,name')
            ->completed()
            ->latest('conducted_at')
            ->take(5)
            ->get()
            ->map(fn ($lesson) => [
                'id'           => $lesson->id,
                'title'        => $lesson->title,
                'conducted_at' => $lesson->conducted_at?->format('d M'),
                'professor'    => $lesson->professor->name,
                'class_name'   => $lesson->turmaClass->name,
                'status'       => $lesson->status,
            ]);

        $enrolledClasses = $user->enrolledClasses()
            ->with('professor:id,name')
            ->get()
            ->map(fn ($class) => [
                'id'        => $class->id,
                'name'      => $class->name,
                'professor' => $class->professor->name,
            ]);

        // Compute warning level
        $warning = null;
        if ($activePackage) {
            $remaining = $activePackage->remaining;
            if ($remaining <= 1 && $remaining > 0) {
                $warning = 'last_lesson';
            }
        } else {
            // Check if they ever had a package (exhausted vs brand new)
            $hasAnyPackage = $user->lessonPackages()->exists();
            $warning = $hasAnyPackage ? 'exhausted' : 'no_package';
        }

        return [
            'activePackage' => $activePackage ? [
                'id'            => $activePackage->id,
                'total_lessons' => $activePackage->total_lessons,
                'used_lessons'  => $activePackage->used_lessons,
                'remaining'     => $activePackage->remaining,
                'price'         => $activePackage->price,
                'currency'      => $activePackage->currency,
                'is_paid'       => $activePackage->isPaid(),
                'expires_at'    => $activePackage->expires_at?->format('d/m/Y'),
                'warning'       => $warning,
            ] : null,
            'warning'        => $warning,
            'recentLessons'  => $recentLessons,
            'enrolledClasses' => $enrolledClasses,
            'stats'          => [
                'totalLessonsUsed' => $user->lessons()->completed()->count(),
                'remaining'        => $activePackage?->remaining ?? 0,
                'nextPaymentDue'   => $warning === 'last_lesson' || $warning === 'exhausted',
            ],
            'progress' => (new GetProgressStatsAction)->execute($user),
            'payment_history' => $user->payments()
                ->with('lessonPackage:id,total_lessons')
                ->latest('paid_at')
                ->limit(5)
                ->get()
                ->map(fn (Payment $p) => [
                    'id'            => $p->id,
                    'amount'        => (float) $p->amount,
                    'method'        => $p->method,
                    'paid_at'       => $p->paid_at?->format('d/m/Y'),
                    'total_lessons' => $p->lessonPackage?->total_lessons,
                ]),
        ];
    }
}
