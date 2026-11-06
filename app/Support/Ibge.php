<?php

namespace App\Support;

/** Código de município do IBGE (7 dígitos: 2 da UF + 4 do município + 1 dígito verificador). */
class Ibge
{
    private const UF = [
        '11' => 'RO', '12' => 'AC', '13' => 'AM', '14' => 'RR', '15' => 'PA', '16' => 'AP', '17' => 'TO', '21' => 'MA', '22' => 'PI',
        '23' => 'CE', '24' => 'RN', '25' => 'PB', '26' => 'PE', '27' => 'AL', '28' => 'SE', '29' => 'BA', '31' => 'MG', '32' => 'ES',
        '33' => 'RJ', '35' => 'SP', '41' => 'PR', '42' => 'SC', '43' => 'RS', '50' => 'MS', '51' => 'MT', '52' => 'GO', '53' => 'DF',
    ];

    /** Dígito verificador: pesos 1,2,1,2,1,2 nos 6 primeiros; soma dos algarismos dos produtos; DV = (10 − soma mod 10) mod 10. */
    public static function checkDigit(string $six): int
    {
        $sum = 0;
        foreach (str_split($six) as $i => $d) {
            $sum += array_sum(str_split((string) ((int) $d * ($i % 2 === 0 ? 1 : 2))));
        }

        return (10 - $sum % 10) % 10;
    }

    public static function isValid(string $code): bool
    {
        return (bool) preg_match('/^\d{7}$/', $code)
            && isset(self::UF[substr($code, 0, 2)])
            && self::checkDigit(substr($code, 0, 6)) === (int) $code[6];
    }

    public static function uf(string $code): ?string
    {
        return self::UF[substr($code, 0, 2)] ?? null;
    }
}
