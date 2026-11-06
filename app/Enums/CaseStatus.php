<?php

namespace App\Enums;

enum CaseStatus: string
{
    case Recebido = 'recebido';
    case EmEscuta = 'em_escuta';
    case Encaminhado = 'encaminhado';
    case EmAcompanhamento = 'em_acompanhamento';
    case Encerrado = 'encerrado';

    public function label(): string
    {
        return match ($this) {
            self::Recebido => 'Recebido',
            self::EmEscuta => 'Em escuta',
            self::Encaminhado => 'Encaminhado',
            self::EmAcompanhamento => 'Em acompanhamento',
            self::Encerrado => 'Encerrado',
        };
    }
}
