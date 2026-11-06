<?php

namespace App\Enums;

enum ReferralStatus: string
{
    case Pendente = 'pendente';
    case Enviado = 'enviado';
    case Recebido = 'recebido';
    case ComRetorno = 'com_retorno';
    case SemRetorno = 'sem_retorno';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente de envio',
            self::Enviado => 'Enviado',
            self::Recebido => 'Recebimento confirmado',
            self::ComRetorno => 'Retorno recebido',
            self::SemRetorno => 'Sem retorno (cobrar)',
        };
    }
}
