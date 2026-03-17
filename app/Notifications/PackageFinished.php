<?php

namespace App\Notifications;

use App\Models\LessonPackage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PackageFinished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly LessonPackage $package) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pacote de aulas esgotado')
            ->greeting("Olá, {$notifiable->name}!")
            ->line('Seu pacote de aulas foi **totalmente utilizado**.')
            ->line('Para continuar seus estudos, renove seu pacote com seu professor.')
            ->action('Ver meu dashboard', url('/dashboard'))
            ->salutation('Até a próxima aula!');
    }
}
