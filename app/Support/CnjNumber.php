<?php

namespace App\Support;

/**
 * Numeração única de processos judiciais (Resolução CNJ 65/2008):
 * NNNNNNN-DD.AAAA.J.TR.OOOO, com DD = 98 − ((NNNNNNN AAAA J TR OOOO 00) mod 97).
 */
class CnjNumber
{
    private const SEGMENTS = [
        '1' => 'Supremo Tribunal Federal', '2' => 'Conselho Nacional de Justiça', '3' => 'Superior Tribunal de Justiça',
        '4' => 'Justiça Federal', '5' => 'Justiça do Trabalho', '6' => 'Justiça Eleitoral',
        '7' => 'Justiça Militar da União', '8' => 'Justiça Estadual', '9' => 'Justiça Militar Estadual',
    ];

    /** Código TR da Justiça Estadual → UF do Tribunal de Justiça. */
    private const TJ_UF = [
        '01' => 'AC', '02' => 'AL', '03' => 'AP', '04' => 'AM', '05' => 'BA', '06' => 'CE', '07' => 'DF', '08' => 'ES',
        '09' => 'GO', '10' => 'MA', '11' => 'MT', '12' => 'MS', '13' => 'MG', '14' => 'PA', '15' => 'PB', '16' => 'PR',
        '17' => 'PE', '18' => 'PI', '19' => 'RJ', '20' => 'RN', '21' => 'RS', '22' => 'RO', '23' => 'RR', '24' => 'SC',
        '25' => 'SE', '26' => 'SP', '27' => 'TO',
    ];

    /** Aceita com ou sem pontuação. Devolve as partes ou null se inválido. */
    public static function parse(string $raw): ?array
    {
        $d = preg_replace('/\D/', '', $raw);
        if (strlen($d) !== 20) {
            return null;
        }
        [$n, $dv, $ano, $j, $tr, $o] = [substr($d, 0, 7), substr($d, 7, 2), substr($d, 9, 4), $d[13], substr($d, 14, 2), substr($d, 16, 4)];

        if (self::checkDigit($n, $ano, $j, $tr, $o) !== (int) $dv) {
            return null;
        }

        return compact('n', 'dv', 'ano', 'j', 'tr', 'o');
    }

    public static function isValid(string $raw): bool
    {
        return self::parse($raw) !== null;
    }

    public static function format(string $raw): ?string
    {
        $p = self::parse($raw);

        return $p ? "{$p['n']}-{$p['dv']}.{$p['ano']}.{$p['j']}.{$p['tr']}.{$p['o']}" : null;
    }

    /** Descrição do órgão a partir de J e TR (informativa). */
    public static function court(string $raw): ?string
    {
        $p = self::parse($raw);
        if (! $p) {
            return null;
        }
        $seg = self::SEGMENTS[$p['j']] ?? 'Segmento desconhecido';

        return $p['j'] === '8' && isset(self::TJ_UF[$p['tr']]) ? "{$seg} · TJ{$p['tr']}→".self::TJ_UF[$p['tr']] : $seg;
    }

    public static function checkDigit(string $n, string $ano, string $j, string $tr, string $o): int
    {
        // mod 97 em partes para não depender de inteiros de 64 bits nem de bcmath.
        $r = 0;
        foreach (str_split($n.$ano.$j.$tr.$o.'00', 5) as $chunk) {
            $r = (int) ($r.$chunk) % 97;
        }

        return 98 - $r;
    }
}
