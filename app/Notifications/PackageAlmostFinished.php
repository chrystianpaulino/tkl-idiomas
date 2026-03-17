<?php

namespace App\Notifications;

use App\Models\LessonPackage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PackageAlmostFinished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly LessonPackage $package) {}

    public function via(object $notifiable): array
    {
        return ['mail']; // Add 'whatsapp' here later when WhatsAppChannel is implemented
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Sua última aula — renove seu pacote')
            ->greeting("Olá, {$notifiable->name}!")
            ->line('Você tem apenas **1 aula restante** no seu pacote atual.')
            ->line('Entre em contato com seu professor para renovar e continuar seus estudos.')
            ->action('Ver meu dashboard', url('/dashboard'))
            ->salutation('Até a próxima aula!');
    }
}
