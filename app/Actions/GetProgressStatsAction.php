<?php

namespace App\Actions;

use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Computes gamification and progress statistics for a student's dashboard.
 *
 * Calculates lessons completed, hours studied (from schedule durations or
 * 60-min default), consecutive weekly streak, and progress toward the next
 * lesson milestone. Uses SQLite-compatible strftime for week grouping.
 *
 * Called by GetDashboardStatsAction for the student (aluno) dashboard view.
 */
class GetProgressStatsAction
{
    /** @var int[] Lesson count milestones for gamification progress tracking */
    private const MILESTONES = [10, 20, 30, 40, 50, 75, 100, 150, 200];

    /**
     * @param User $student The student to compute progress stats for
     * @return array{lessonsCompleted: int, hoursStudied: float, currentStreak: int, nextMilestone: int|null, milestoneProgress: int|float}
     */
    public function execute(User $student): array
    {
        $lessonsCompleted = Lesson::where('student_id', $student->id)
            ->where('status', 'completed')
            ->count();

        $hoursStudied = $this->calculateHoursStudied($student->id);
        $currentStreak = $this->calculateStreak($student->id);
        [$nextMilestone, $milestoneProgress] = $this->calculateMilestone($lessonsCompleted);

        return [
            'lessonsCompleted' => $lessonsCompleted,
            'hoursStudied'     => $hoursStudied,
            'currentStreak'    => $currentStreak,
            'nextMilestone'    => $nextMilestone,
            'milestoneProgress' => $milestoneProgress,
        ];
    }

    /**
     * Calculate total study hours by summing schedule durations for lessons that
     * came from a schedule, and assuming 60 minutes for ad-hoc lessons.
     */
    private function calculateHoursStudied(int $studentId): float
    {
        $minutesFromSchedules = Lesson::where('lessons.student_id', $studentId)
            ->where('lessons.status', 'completed')
            ->join('scheduled_lessons', 'scheduled_lessons.lesson_id', '=', 'lessons.id')
            ->join('schedules', 'schedules.id', '=', 'scheduled_lessons.schedule_id')
            ->whereNotNull('scheduled_lessons.schedule_id')
            ->sum('schedules.duration_minutes');

        $lessonsWithoutSchedule = Lesson::where('student_id', $studentId)
            ->where('status', 'completed')
            ->whereNotIn('id', function ($q) {
                $q->select('lesson_id')
                  ->from('scheduled_lessons')
                  ->whereNotNull('lesson_id')
                  ->whereNotNull('schedule_id');
            })
            ->count();

        $totalMinutes = $minutesFromSchedules + ($lessonsWithoutSchedule * 60);

        return round($totalMinutes / 60, 1);
    }

    /**
     * Calculate the student's current weekly lesson streak (consecutive weeks
     * with at least one completed lesson, counting backward from the current week).
     * Returns 0 if the most recent lesson week is older than last week.
     */
    private function calculateStreak(int $studentId): int
    {
        $weeks = Lesson::where('student_id', $studentId)
            ->where('status', 'completed')
            ->selectRaw("strftime('%Y-%W', conducted_at) as week")
            ->distinct()
            ->orderByRaw("week DESC")
            ->pluck('week')
            ->toArray();

        if (empty($weeks)) {
            return 0;
        }

        $streak = 1;
        $current = Carbon::now()->startOfWeek();
        $currentWeekKey = $current->format('Y') . '-' . str_pad($current->format('W'), 2, '0', STR_PAD_LEFT);

        $mostRecent = $weeks[0];
        $lastWeekKey = $current->copy()->subWeek()->format('Y') . '-' . str_pad($current->copy()->subWeek()->format('W'), 2, '0', STR_PAD_LEFT);

        if ($mostRecent !== $currentWeekKey && $mostRecent !== $lastWeekKey) {
            return 0;
        }

        for ($i = 1; $i < count($weeks); $i++) {
            $expected = $current->copy()->subWeeks($i)->format('Y') . '-' . str_pad($current->copy()->subWeeks($i)->format('W'), 2, '0', STR_PAD_LEFT);
            if ($weeks[$i] === $expected) {
                $streak++;
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Find the next milestone and compute percentage progress toward it.
     *
     * @param int $completed Total completed lessons
     * @return array{0: int|null, 1: int|float} [nextMilestone, progressPercent] -- null milestone means all milestones achieved
     */
    private function calculateMilestone(int $completed): array
    {
        foreach (self::MILESTONES as $milestone) {
            if ($completed < $milestone) {
                $previous = 0;
                foreach (self::MILESTONES as $m) {
                    if ($m < $milestone) {
                        $previous = $m;
                    }
                }
                $range = $milestone - $previous;
                $progress = $range > 0 ? round((($completed - $previous) / $range) * 100) : 100;
                return [$milestone, $progress];
            }
        }

        return [null, 100];
    }
}
