<?php

namespace App\Enums;

enum Role: string
{
    case Coordenacao = 'coordenacao';
    case Psicologo = 'psicologo';
    case AssistenteSocial = 'assistente_social';
    case Recepcao = 'recepcao';

    public function label(): string
    {
        return match ($this) {
            self::Coordenacao => 'Coordenação',
            self::Psicologo => 'Psicólogo(a)',
            self::AssistenteSocial => 'Assistente Social',
            self::Recepcao => 'Recepção / Administrativo',
        };
    }

    /** Profissionais habilitados a realizar a escuta e ler seus relatos. */
    public function isTechnical(): bool
    {
        return in_array($this, [self::Psicologo, self::AssistenteSocial], true);
    }
}
