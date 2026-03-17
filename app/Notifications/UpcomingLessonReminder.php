<?php

namespace App\Notifications;

use App\Models\ScheduledLesson;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UpcomingLessonReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ScheduledLesson $scheduledLesson) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = $this->scheduledLesson->scheduled_at->format('d/m/Y');
        $time = $this->scheduledLesson->scheduled_at->format('H:i');
        $className = $this->scheduledLesson->turmaClass->name ?? 'sua turma';

        return (new MailMessage)
            ->subject("Lembrete: aula amanhã às {$time}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Lembrete: você tem uma aula de **{$className}** amanhã ({$date}) às **{$time}**.")
            ->action('Ver detalhes', url('/dashboard'))
            ->salutation('Até amanhã!');
    }
}
