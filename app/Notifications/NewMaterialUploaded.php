<?php

namespace App\Notifications;

use App\Models\Material;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewMaterialUploaded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Material $material) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $className = $this->material->turmaClass->name ?? 'sua turma';

        return (new MailMessage)
            ->subject("Novo material disponível — {$this->material->title}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Seu professor adicionou um novo material à turma **{$className}**:")
            ->line("**{$this->material->title}**")
            ->when($this->material->description, fn ($mail) => $mail->line($this->material->description))
            ->action('Acessar material', url("/classes/{$this->material->class_id}"))
            ->salutation('Bons estudos!');
    }
}
