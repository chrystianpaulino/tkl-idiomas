<?php

namespace App\Console\Commands;

use App\Actions\Schedules\GenerateScheduledLessonsAction;
use Illuminate\Console\Command;

class GenerateScheduledLessons extends Command
{
    protected $signature = 'schedules:generate {--weeks=4 : Number of weeks ahead to generate}';
    protected $description = 'Generate upcoming scheduled lesson slots for all active schedules';

    public function handle(GenerateScheduledLessonsAction $action): int
    {
        $weeks = (int) $this->option('weeks');
        $this->info("Generating scheduled lessons for {$weeks} weeks ahead...");

        $count = $action->executeForAll($weeks);

        $this->info("Created {$count} new scheduled lesson slots.");
        return Command::SUCCESS;
    }
}
