<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso ao profissional: só protocolos, nunca nomes ou relatos. */
class CasosAtribuidos extends Notification
{
    /** @param list<string> $protocolos */
    public function __construct(public array $protocolos) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('SIGIEP · '.count($this->protocolos).' caso(s) atribuído(s) a você')
            ->line('A coordenação atribuiu a você: '.implode(', ', $this->protocolos).'.')
            ->action('Abrir a lista de espera', route('fila.index'));
    }
}
