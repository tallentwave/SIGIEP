<?php

namespace App\Enums;

enum Condition: string
{
    case Vitima = 'vitima';
    case Testemunha = 'testemunha';

    public function label(): string
    {
        return $this === self::Vitima ? 'Vítima' : 'Testemunha';
    }
}
