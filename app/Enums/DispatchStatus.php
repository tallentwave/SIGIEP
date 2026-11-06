<?php

namespace App\Enums;

enum DispatchStatus: string
{
    case Gerado = 'gerado';       // arquivo/documento gerado, ainda não confirmado como entregue
    case Concluido = 'concluido'; // SINAN: nº de notificação recebido; Justiça: enviado/protocolado
    case Dispensado = 'dispensado';

    public function label(): string
    {
        return match ($this) {
            self::Gerado => 'Gerado, aguardando confirmação',
            self::Concluido => 'Concluído',
            self::Dispensado => 'Dispensado',
        };
    }
}
