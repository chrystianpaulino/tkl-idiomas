<?php

namespace App\Console\Commands;

use App\Models\ScheduledLesson;
use App\Notifications\UpcomingLessonReminder;
use Illuminate\Console\Command;

class SendLessonReminders extends Command
{
    protected $signature = 'notifications:send-lesson-reminders';

    protected $description = 'Send 24-hour reminders for tomorrow\'s scheduled lessons';

    public function handle(): int
    {
        $tomorrow = now()->addDay();

        $scheduledLessons = ScheduledLesson::with(['turmaClass.students'])
            ->where('status', 'scheduled')
            ->whereDate('scheduled_at', $tomorrow->toDateString())
            ->get();

        $count = 0;
        foreach ($scheduledLessons as $scheduledLesson) {
            foreach ($scheduledLesson->turmaClass->students as $student) {
                $student->notify(new UpcomingLessonReminder($scheduledLesson));
                $count++;
            }
        }

        $this->info("Sent {$count} lesson reminders.");

        return Command::SUCCESS;
    }
}
