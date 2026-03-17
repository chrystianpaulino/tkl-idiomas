<?php

namespace App\Actions\Lessons;

use App\Models\Lesson;
use App\Models\LessonPackage;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterLessonAction
{
    public function execute(TurmaClass $turmaClass, User $student, User $professor, array $data): Lesson
    {
        return DB::transaction(function () use ($turmaClass, $student, $professor, $data) {
            // Lock the earliest-expiring active package for this student
            $package = LessonPackage::where('student_id', $student->id)
                ->active()
                ->orderBy('expires_at')
                ->lockForUpdate()
                ->firstOrFail();

            // Re-verify package is still active after lock (race condition guard)
            if (!$package->isActive()) {
                throw new \RuntimeException('No active lesson package available for this student.');
            }

            $package->increment('used_lessons');
            $package->refresh(); // reload to get updated used_lessons

            // Notify student about package status AFTER the transaction's credit decrement
            if ($package->isExhausted()) {
                $student->notify(new \App\Notifications\PackageFinished($package));
            } elseif ($package->remaining === 1) {
                $student->notify(new \App\Notifications\PackageAlmostFinished($package));
            }

            return Lesson::create([
                'class_id' => $turmaClass->id,
                'student_id' => $student->id,
                'professor_id' => $professor->id,
                'package_id' => $package->id,
                'title' => $data['title'],
                'notes' => $data['notes'] ?? null,
                'conducted_at' => $data['conducted_at'] ?? now(),
            ]);
        });
    }
}
