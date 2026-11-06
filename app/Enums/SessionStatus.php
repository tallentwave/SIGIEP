<?php

namespace App\Enums;

enum SessionStatus: string
{
    case Agendada = 'agendada';
    case Realizada = 'realizada';
    case Faltou = 'faltou';
    case Remarcada = 'remarcada';
    case Cancelada = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Agendada => 'Agendada',
            self::Realizada => 'Realizada',
            self::Faltou => 'Não compareceu',
            self::Remarcada => 'Remarcada',
            self::Cancelada => 'Cancelada',
        };
    }
}
