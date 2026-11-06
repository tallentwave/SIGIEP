<?php

namespace App\Enums;

enum ReferralDestination: string
{
    case ConselhoTutelar = 'conselho_tutelar';
    case Creas = 'creas';
    case Cras = 'cras';
    case Saude = 'saude';
    case MinisterioPublico = 'ministerio_publico';
    case Policia = 'policia';
    case Judiciario = 'judiciario';
    case Educacao = 'educacao';
    case Outro = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::ConselhoTutelar => 'Conselho Tutelar',
            self::Creas => 'CREAS',
            self::Cras => 'CRAS',
            self::Saude => 'Rede de Saúde',
            self::MinisterioPublico => 'Ministério Público',
            self::Policia => 'Delegacia / Polícia',
            self::Judiciario => 'Poder Judiciário / Vara',
            self::Educacao => 'Educação',
            self::Outro => 'Outro',
        };
    }
}
