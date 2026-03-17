<?php

namespace App\Actions\Schedules;

use App\Models\Schedule;
use App\Models\TurmaClass;

class CreateScheduleAction
{
    public function execute(TurmaClass $turmaClass, array $data): Schedule
    {
        return Schedule::create([
            'class_id'         => $turmaClass->id,
            'weekday'          => $data['weekday'],
            'start_time'       => $data['start_time'],
            'duration_minutes' => $data['duration_minutes'] ?? 60,
            'active'           => true,
        ]);
    }
}
