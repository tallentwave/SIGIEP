<?php

namespace App\Services;

use App\Enums\Condition;
use App\Models\CaseRecord;
use Carbon\CarbonInterface;

/**
 * Converte um caso para as colunas (nomes DBF) e códigos da ficha de
 * Violência Interpessoal/Autoprovocada do SINAN NET (dicionário v5.1).
 *
 * IMPORTANTE: o SINAN não oferece API pública de envio. Este arquivo serve
 * para a carga assistida pela vigilância epidemiológica do município (quem
 * opera o SINAN). Campos sem dado na ficha saem como 9 (Ignorado) e são
 * listados como aviso para o profissional completar antes do envio.
 */
class SinanExporter
{
    public const COLUMNS = [
        'NU_NOTIFIC', 'TP_NOT', 'ID_AGRAVO', 'DT_NOTIFIC', 'SG_UF_NOT', 'ID_MUNICIP', 'TP_UNI_EXT', 'NM_UNI_EXT', 'CO_UNI_EXT',
        'DT_OCOR', 'NM_PACIENT', 'DT_NASC', 'NU_IDADE_N', 'CS_SEXO', 'CS_RACA', 'NM_MAE_PA', 'SG_UF', 'ID_MN_RESI', 'NM_BAIRRO', 'NM_LOGRAD',
        'SG_UF_OCOR', 'ID_MN_OCOR', 'ZONA_OCOR', 'LOCAL_OCOR', 'LOCAL_ESPE', 'OUT_VEZES',
        'VIOL_FISIC', 'VIOL_PSICO', 'VIOL_SEXU', 'VIOL_NEGLI', 'VIOL_FINAN', 'VIOL_OUTR', 'VIOL_ESPEC',
        'AG_FORCA', 'AG_ENFOR', 'AG_OBJETO', 'AG_CORTE', 'AG_QUENTE', 'AG_ENVEN', 'AG_FOGO', 'AG_AMEACA', 'AG_OUTROS',
        'SEX_ASSEDI', 'SEX_ESTUPR', 'SEX_PORNO', 'SEX_EXPLO', 'SEX_OUTRO', 'SEX_ESPEC',
        'NUM_ENVOL', 'REL_PAI', 'REL_MAE', 'REL_PAD', 'REL_MAD', 'REL_CONJ', 'REL_IRMAO', 'REL_CONHEC', 'REL_DESCO', 'REL_CUIDA',
        'REL_INST', 'REL_OUTROS', 'REL_ESPEC', 'AUTOR_SEXO', 'AUTOR_ALCO',
        'ENC_SAUDE', 'ASSIST_SOC', 'REDE_EDUCA', 'CONS_TUTEL', 'DELEG', 'INFAN_JUV', 'DS_OBS',
    ];

    private const LOCAL = ['residencia' => '01', 'habitacao_coletiva' => '02', 'escola' => '03', 'bar' => '05',
        'via_publica' => '06', 'comercio' => '07', 'outro' => '09', 'internet' => '09', 'ignorado' => '99'];

    private const ZONA = ['urbana' => '1', 'rural' => '2', 'periurbana' => '3', 'ignorado' => '9'];

    private const SIM_NAO = ['sim' => '1', 'nao' => '2', 'ignorado' => '9'];

    private const RACA = ['branca' => '1', 'preta' => '2', 'amarela' => '3', 'parda' => '4', 'indigena' => '5', 'ignorado' => '9'];

    private const AGRESSAO = ['forca_corporal' => 'AG_FORCA', 'enforcamento' => 'AG_ENFOR', 'objeto_contundente' => 'AG_OBJETO',
        'perfurocortante' => 'AG_CORTE', 'queimadura' => 'AG_QUENTE', 'envenenamento' => 'AG_ENVEN', 'arma_fogo' => 'AG_FOGO',
        'ameaca' => 'AG_AMEACA', 'outro' => 'AG_OUTROS'];

    private const SEXUAL = ['assedio' => 'SEX_ASSEDI', 'estupro' => 'SEX_ESTUPR', 'pornografia_infantil' => 'SEX_PORNO',
        'exploracao_sexual' => 'SEX_EXPLO', 'atentado_pudor' => 'SEX_OUTRO', 'outro' => 'SEX_OUTRO'];

    private const VINCULO = ['pai' => 'REL_PAI', 'mae' => 'REL_MAE', 'padrasto' => 'REL_PAD', 'madrasta' => 'REL_MAD',
        'conjuge_namorado' => 'REL_CONJ', 'irmao' => 'REL_IRMAO', 'amigo_conhecido' => 'REL_CONHEC', 'desconhecido' => 'REL_DESCO',
        'cuidador' => 'REL_CUIDA', 'professor_instituicao' => 'REL_INST', 'outro_familiar' => 'REL_OUTROS', 'outro' => 'REL_OUTROS'];

    private const ENCAMINHAMENTO = [
        'conselho_tutelar' => 'CONS_TUTEL', 'saude' => 'ENC_SAUDE', 'creas' => 'ASSIST_SOC', 'cras' => 'ASSIST_SOC',
        'educacao' => 'REDE_EDUCA', 'policia' => 'DELEG', 'judiciario' => 'INFAN_JUV',
    ];

    /** @return array{row: array<string,string>, warnings: list<string>, errors: list<string>} */
    public function map(CaseRecord $case): array
    {
        $case->loadMissing('child', 'municipality', 'referrals');
        $m = $case->municipality;
        $c = $case->child;
        $f = $case->ficha ?? [];
        $types = $case->violence_types ?? [];
        $warnings = [];
        $errors = [];

        if (blank($m->ibge_code)) {
            $errors[] = 'Município sem código IBGE (cadastre em Municípios).';
        }
        if (blank($m->sinan_unit_name)) {
            $errors[] = 'Unidade notificadora não configurada (nome/código em Municípios).';
        }
        if ($case->isAnonymized()) {
            $errors[] = 'Caso anonimizado: dados pessoais não existem mais.';
        }
        if (! $c->birth_date) {
            $errors[] = 'Data de nascimento ausente (campo obrigatório).';
        }

        $ibge6 = substr(preg_replace('/\D/', '', (string) $m->ibge_code), 0, 6);
        $row = array_fill_keys(self::COLUMNS, '');
        $ign = function (string $col, string $why) use (&$row, &$warnings) {
            $row[$col] = '9';
            $warnings[] = "{$col}: sem dado na ficha ({$why}); enviado como 9 – Ignorado.";
        };

        $row['TP_NOT'] = '2'; // individual
        $row['ID_AGRAVO'] = config('sigiep.sinan.agravo', 'Y09');
        $row['DT_NOTIFIC'] = now()->format('d/m/Y');
        $row['SG_UF_NOT'] = $m->uf;
        $row['ID_MUNICIP'] = $ibge6;
        $row['TP_UNI_EXT'] = (string) $m->sinan_unit_type;
        $row['NM_UNI_EXT'] = (string) $m->sinan_unit_name;
        $row['CO_UNI_EXT'] = (string) $m->sinan_unit_code;

        // Ocorrência
        $row['DT_OCOR'] = $case->violence_date?->format('d/m/Y') ?? '';
        if (! $case->violence_date) {
            $errors[] = 'Data da ocorrência ausente (campo obrigatório).';
        }
        $row['SG_UF_OCOR'] = $m->uf;
        $row['ID_MN_OCOR'] = $ibge6;
        $local = $f['local_ocorrencia'] ?? null;
        $row['LOCAL_OCOR'] = $local ? self::LOCAL[$local] : '99';
        if (! $local) {
            $warnings[] = 'LOCAL_OCOR: sem dado na ficha; enviado como 99 – Ignorado.';
        }
        if (in_array($local, ['outro', 'internet'], true)) {
            $row['LOCAL_ESPE'] = $local === 'internet' ? 'Ambiente virtual' : ($f['local_outro'] ?? 'Outro');
        }
        $row['ZONA_OCOR'] = self::ZONA[$f['zona'] ?? 'ignorado'];
        $row['OUT_VEZES'] = self::SIM_NAO[$f['ocorreu_outras_vezes'] ?? 'ignorado'];

        // Paciente
        $row['NM_PACIENT'] = (string) $c->name;
        $row['DT_NASC'] = $c->birth_date?->format('d/m/Y') ?? '';
        $row['NU_IDADE_N'] = $this->age($c->birth_date, $case->violence_date ?? now());
        $row['CS_SEXO'] = $this->sex($c->sex);
        if ($row['CS_SEXO'] === 'I') {
            $warnings[] = 'CS_SEXO: não identificado no cadastro; enviado como I – Ignorado.';
        }
        $row['CS_RACA'] = self::RACA[$f['raca_cor'] ?? 'ignorado'];
        $row['NM_MAE_PA'] = (string) $c->mother_name;
        $row['SG_UF'] = $m->uf;
        $row['ID_MN_RESI'] = $ibge6;
        $row['NM_BAIRRO'] = (string) $c->neighborhood;
        $row['NM_LOGRAD'] = (string) $c->address;

        // Tipo de violência (campo 56): Sim/Não explícitos, já que o caso informa os tipos.
        $yes = fn (string $t) => in_array($t, $types, true) ? '1' : '2';
        $row['VIOL_FISIC'] = $yes('fisica');
        $row['VIOL_PSICO'] = $yes('psicologica');
        $row['VIOL_SEXU'] = $yes('sexual');
        $row['VIOL_NEGLI'] = $yes('negligencia');
        $row['VIOL_FINAN'] = $yes('patrimonial');
        $row['VIOL_OUTR'] = in_array('institucional', $types, true) ? '1' : '2';
        if ($row['VIOL_OUTR'] === '1') {
            $row['VIOL_ESPEC'] = 'Violência institucional';
        }

        // Meio de agressão (57)
        $meios = $f['meio_agressao'] ?? [];
        foreach (self::AGRESSAO as $key => $col) {
            $row[$col] = $meios ? (in_array($key, $meios, true) ? '1' : '2') : '9';
        }
        if (! $meios) {
            $warnings[] = 'AG_*: meio de agressão não informado; enviado como 9 – Ignorado.';
        }

        // Violência sexual (58): 8 = não se aplica quando não houve violência sexual
        $sexual = in_array('sexual', $types, true);
        $tipos = $f['tipo_sexual'] ?? [];
        foreach (array_unique(array_values(self::SEXUAL)) as $col) {
            $row[$col] = ! $sexual ? '8' : ($tipos ? '2' : '9');
        }
        if ($sexual) {
            foreach ($tipos as $t) {
                $row[self::SEXUAL[$t]] = '1';
            }
            if (in_array('atentado_pudor', $tipos, true)) {
                $row['SEX_ESPEC'] = 'Atentado violento ao pudor';
            }
            if (! $tipos) {
                $warnings[] = 'SEX_*: tipo de violência sexual não informado; enviado como 9 – Ignorado.';
            }
        }

        // Provável autor (59–64)
        $row['NUM_ENVOL'] = ['um' => '1', 'dois_mais' => '2', 'ignorado' => '9'][$f['num_envolvidos'] ?? 'ignorado'];
        $vinculo = $f['vinculo_autor'] ?? null;
        foreach (array_unique(array_values(self::VINCULO)) as $col) {
            $row[$col] = $vinculo ? '2' : '9';
        }
        if ($vinculo) {
            $row[self::VINCULO[$vinculo]] = '1';
            if (in_array($vinculo, ['outro_familiar', 'outro'], true)) {
                $row['REL_ESPEC'] = $vinculo === 'outro_familiar' ? 'Outro familiar' : 'Outro';
            }
            if ($vinculo === 'conjuge_namorado') {
                $warnings[] = 'REL_CONJ: a ficha agrupa cônjuge/namorado(a); confirme se é cônjuge, ex-cônjuge, namorado(a) ou ex-namorado(a).';
            }
        } else {
            $warnings[] = 'REL_*: vínculo do provável autor não informado; enviado como 9 – Ignorado.';
        }
        $row['AUTOR_SEXO'] = ['masculino' => '1', 'feminino' => '2', 'ambos' => '3', 'ignorado' => '9'][$f['sexo_autor'] ?? 'ignorado'];
        $row['AUTOR_ALCO'] = self::SIM_NAO[$f['suspeita_alcool'] ?? 'ignorado'];

        // Encaminhamentos (65): 9 se ainda não há nenhum registrado
        $dests = $case->referrals->map(fn ($r) => $r->destination->value)->unique();
        $encCols = array_unique(array_values(self::ENCAMINHAMENTO));
        foreach ($encCols as $col) {
            $row[$col] = $dests->isEmpty() ? '9' : '2';
        }
        foreach ($dests as $d) {
            if (isset(self::ENCAMINHAMENTO[$d])) {
                $row[self::ENCAMINHAMENTO[$d]] = '1';
            }
        }
        if ($dests->isEmpty()) {
            $warnings[] = 'ENC_*: nenhum encaminhamento registrado ainda; enviado como 9 – Ignorado.';
        }

        $row['DS_OBS'] = 'Gerado pelo SIGIEP · protocolo '.$case->protocol;

        return ['row' => $row, 'warnings' => array_values(array_unique($warnings)), 'errors' => array_values(array_unique($errors))];
    }

    /** CSV (UTF-8 com BOM, separador ";", cabeçalho = nomes DBF). */
    public function csv(iterable $rows): string
    {
        $h = fopen('php://temp', 'r+');
        fwrite($h, "\xEF\xBB\xBF");
        fputcsv($h, self::COLUMNS, ';');
        foreach ($rows as $row) {
            fputcsv($h, array_map(fn ($c) => $this->safe((string) ($row[$c] ?? '')), self::COLUMNS), ';');
        }
        rewind($h);

        return stream_get_contents($h);
    }

    /** Evita injeção de fórmula ao abrir o CSV no Excel/Calc (=, +, -, @ no início). */
    private function safe(string $v): string
    {
        return $v !== '' && str_contains("=+-@\t\r", $v[0]) && ! preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $v) ? "'".$v : $v;
    }

    /** NU_IDADE_N do SINAN: 1º dígito = unidade (1 hora, 2 dia, 3 mês, 4 ano) + 3 dígitos. */
    private function age(?CarbonInterface $birth, CarbonInterface $ref): string
    {
        if (! $birth) {
            return '';
        }
        $years = (int) floor(abs($birth->diffInYears($ref)));
        if ($years >= 1) {
            return '4'.str_pad((string) min($years, 999), 3, '0', STR_PAD_LEFT);
        }
        $months = (int) floor(abs($birth->diffInMonths($ref)));
        if ($months >= 1) {
            return '3'.str_pad((string) $months, 3, '0', STR_PAD_LEFT);
        }

        return '2'.str_pad((string) (int) floor(abs($birth->diffInDays($ref))), 3, '0', STR_PAD_LEFT);
    }

    private function sex(?string $sex): string
    {
        $s = mb_strtolower(trim((string) $sex));

        return match (true) {
            $s === '' => 'I',
            str_starts_with($s, 'm') => 'M',
            str_starts_with($s, 'f') => 'F',
            default => 'I',
        };
    }

    /** Casos que precisam de notificação: vítima de violência. */
    public static function requires(CaseRecord $case): bool
    {
        return $case->condition === Condition::Vitima && ! $case->isAnonymized();
    }

    /** Prazo interno (dias) conforme o tipo de violência. */
    public static function deadline(CaseRecord $case): CarbonInterface
    {
        $days = config('sigiep.sinan.deadline_days');
        $d = in_array('sexual', $case->violence_types ?? [], true) ? $days['sexual'] : $days['default'];

        return $case->received_at->copy()->addDays((int) $d);
    }
}
