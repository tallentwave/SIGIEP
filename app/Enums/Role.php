<?php

namespace App\Enums;

enum Role: string
{
    case Administrador = 'administrador';
    case Coordenacao = 'coordenacao';
    case Psicologo = 'psicologo';
    case AssistenteSocial = 'assistente_social';
    case Recepcao = 'recepcao';

    public function label(): string
    {
        return match ($this) {
            self::Administrador => 'Administrador do sistema',
            self::Coordenacao => 'Coordenação',
            self::Psicologo => 'Psicólogo(a)',
            self::AssistenteSocial => 'Assistente Social',
            self::Recepcao => 'Recepção / Administrativo',
        };
    }

    public function isAdmin(): bool
    {
        return $this === self::Administrador;
    }

    /** Profissionais habilitados a realizar a escuta e ler seus relatos. */
    public function isTechnical(): bool
    {
        return in_array($this, [self::Psicologo, self::AssistenteSocial], true);
    }
}
