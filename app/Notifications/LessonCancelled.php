<?php

namespace App\Notifications;

use App\Models\ScheduledLesson;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LessonCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ScheduledLesson $scheduledLesson) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = $this->scheduledLesson->scheduled_at->format('d/m/Y \à\s H:i');
        $reason = $this->scheduledLesson->cancelled_reason;

        return (new MailMessage)
            ->subject('Aula cancelada — '.$date)
            ->greeting("Olá, {$notifiable->name}!")
            ->line("A aula do dia **{$date}** foi cancelada.")
            ->when($reason, fn ($mail) => $mail->line("Motivo: {$reason}"))
            ->action('Ver meus horários', url('/dashboard'))
            ->salutation('Até a próxima!');
    }
}
