<?php

namespace App\Notifications;

use App\Models\CaseRecord;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sem dados pessoais no e-mail: apenas protocolo e urgência. */
class NovoCasoPublico extends Notification
{
    public function __construct(public CaseRecord $case) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $m = (new MailMessage)
            ->subject(($this->case->urgent ? '[URGENTE] ' : '').'Nova denúncia pública '.$this->case->protocol)
            ->line("Chegou uma denúncia pública ao SIGIEP ({$this->case->municipality->fullName()}).")
            ->line("Protocolo: {$this->case->protocol}");
        if ($this->case->urgent) {
            $m->line('A pessoa que denunciou indicou RISCO IMEDIATO. Priorize a triagem.');
        }

        return $m->action('Abrir fila de casos', route('casos.index'))
            ->line('Por sigilo, os detalhes só estão disponíveis dentro do sistema.');
    }
}
