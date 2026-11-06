<?php

namespace App\Enums;

/** Formas de violência previstas no art. 4º da Lei 13.431/2017. */
enum ViolenceType: string
{
    case Fisica = 'fisica';
    case Psicologica = 'psicologica';
    case Sexual = 'sexual';
    case Institucional = 'institucional';
    case Patrimonial = 'patrimonial';
    case Negligencia = 'negligencia';
    case Testemunha = 'testemunha';

    public function label(): string
    {
        return match ($this) {
            self::Fisica => 'Violência física',
            self::Psicologica => 'Violência psicológica',
            self::Sexual => 'Violência sexual',
            self::Institucional => 'Violência institucional',
            self::Patrimonial => 'Violência patrimonial',
            self::Negligencia => 'Negligência / abandono',
            self::Testemunha => 'Testemunha de violência',
        };
    }
}
