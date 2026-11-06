<?php

namespace App\Enums;

enum DispatchSystem: string
{
    case Sinan = 'sinan';
    case Justica = 'justica';

    public function label(): string
    {
        return $this === self::Sinan ? 'SINAN (notificação)' : 'Sistema de Justiça';
    }
}
