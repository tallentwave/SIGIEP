<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Resumo diário de prazos. Só protocolos e contagens, nunca nomes ou relatos. */
class AlertasDiarios extends Notification
{
    /** @param array{atrasados: array<string>, escutas: array<string>, fila: array<string>} $dados */
    public function __construct(public array $dados) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $m = (new MailMessage)->subject('SIGIEP · alertas do dia')->line('Resumo de prazos que pedem atenção:');

        if ($this->dados['atrasados']) {
            $m->line('Encaminhamentos sem retorno após o prazo: '.implode(', ', $this->dados['atrasados']));
        }
        if ($this->dados['escutas']) {
            $m->line('Escutas nas próximas 24h: '.implode(', ', $this->dados['escutas']));
        }
        if ($this->dados['fila']) {
            $m->line('Casos aguardando responsável há mais tempo que o limite: '.implode(', ', $this->dados['fila']));
        }

        return $m->action('Abrir o SIGIEP', route('dashboard'));
    }
}
