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
        'NUM_ENVOL', 'REL_PAI', 'REL_MAE', 'REL_PAD', 'REL_MAD', 'REL_CONJ', 'REL_EXCON', 'REL_NAMO', 'REL_EXNAM', 'REL_FILHO', 'REL_IRMAO',
        'REL_CONHEC', 'REL_DESCO', 'REL_CUIDA', 'REL_PATRAO', 'REL_INST', 'REL_POL', 'REL_PROPRI', 'REL_OUTROS', 'REL_ESPEC',
        'AUTOR_SEXO', 'AUTOR_ALCO', 'CICL_VID_AU',
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
        'conjuge' => 'REL_CONJ', 'ex_conjuge' => 'REL_EXCON', 'namorado' => 'REL_NAMO', 'ex_namorado' => 'REL_EXNAM',
        'filho' => 'REL_FILHO', 'irmao' => 'REL_IRMAO', 'amigo_conhecido' => 'REL_CONHEC', 'desconhecido' => 'REL_DESCO',
        'cuidador' => 'REL_CUIDA', 'patrao_chefe' => 'REL_PATRAO', 'professor_instituicao' => 'REL_INST', 'policial' => 'REL_POL',
        'proprietario_estabelecimento' => 'REL_PROPRI', 'outro_familiar' => 'REL_OUTROS', 'outro' => 'REL_OUTROS'];

    private const CICLO = ['crianca' => '1', 'adolescente' => '2', 'jovem' => '3', 'adulta' => '4', 'idosa' => '5', 'ignorado' => '9'];

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
            $row[self::VINCULO[$vinculo] ?? 'REL_OUTROS'] = '1';
            if (in_array($vinculo, ['outro_familiar', 'outro'], true)) {
                $row['REL_ESPEC'] = $vinculo === 'outro_familiar' ? 'Outro familiar' : 'Outro';
            }
        } else {
            $warnings[] = 'REL_*: vínculo do provável autor não informado; enviado como 9 – Ignorado.';
        }
        $row['AUTOR_SEXO'] = ['masculino' => '1', 'feminino' => '2', 'ambos' => '3', 'ignorado' => '9'][$f['sexo_autor'] ?? 'ignorado'];
        $row['AUTOR_ALCO'] = self::SIM_NAO[$f['suspeita_alcool'] ?? 'ignorado'];
        $row['CICL_VID_AU'] = self::CICLO[$f['faixa_autor'] ?? 'ignorado'];

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

    private const SIM_NAO_LABEL = ['1' => 'Sim', '2' => 'Não', '8' => 'Não se aplica', '9' => 'Ignorado'];

    /** Rótulos dos campos de múltipla marcação (números 56, 57, 58, 61 e 65 da ficha). */
    private const GROUPS = [
        '56' => ['Tipo de violência', ['VIOL_FISIC' => 'Física', 'VIOL_PSICO' => 'Psicológica/Moral', 'VIOL_SEXU' => 'Sexual',
            'VIOL_NEGLI' => 'Negligência/Abandono', 'VIOL_FINAN' => 'Financeira/Econômica', 'VIOL_OUTR' => 'Outros']],
        '57' => ['Meio de agressão', ['AG_FORCA' => 'Força corporal/Espancamento', 'AG_ENFOR' => 'Enforcamento', 'AG_OBJETO' => 'Objeto contundente',
            'AG_CORTE' => 'Objeto perfurocortante', 'AG_QUENTE' => 'Substância/objeto quente', 'AG_ENVEN' => 'Envenenamento',
            'AG_FOGO' => 'Arma de fogo', 'AG_AMEACA' => 'Ameaça', 'AG_OUTROS' => 'Outro']],
        '58' => ['Se violência sexual, qual tipo', ['SEX_ASSEDI' => 'Assédio sexual', 'SEX_ESTUPR' => 'Estupro', 'SEX_PORNO' => 'Pornografia infantil',
            'SEX_EXPLO' => 'Exploração sexual', 'SEX_OUTRO' => 'Outro']],
        '61' => ['Relação com a pessoa atendida (provável autor)', ['REL_PAI' => 'Pai', 'REL_MAE' => 'Mãe', 'REL_PAD' => 'Padrasto', 'REL_MAD' => 'Madrasta',
            'REL_CONJ' => 'Cônjuge', 'REL_EXCON' => 'Ex-cônjuge', 'REL_NAMO' => 'Namorado(a)', 'REL_EXNAM' => 'Ex-namorado(a)', 'REL_FILHO' => 'Filho(a)',
            'REL_IRMAO' => 'Irmão(ã)', 'REL_CONHEC' => 'Amigos/conhecidos', 'REL_DESCO' => 'Desconhecido', 'REL_CUIDA' => 'Cuidador(a)',
            'REL_PATRAO' => 'Patrão/chefe', 'REL_INST' => 'Relação institucional', 'REL_POL' => 'Policial/agente da lei',
            'REL_PROPRI' => 'Proprietário de estabelecimento', 'REL_OUTROS' => 'Outros']],
        '65' => ['Encaminhamento', ['ENC_SAUDE' => 'Rede da Saúde', 'ASSIST_SOC' => 'Assistência Social', 'REDE_EDUCA' => 'Rede da Educação',
            'CONS_TUTEL' => 'Conselho Tutelar', 'DELEG' => 'Delegacia', 'INFAN_JUV' => 'Justiça da Infância e da Juventude']],
    ];

    /**
     * Ficha numerada na ordem do dicionário SINAN NET v5.1, para digitação/conferência.
     *
     * @return list<array{n: string, title: string, value?: string, group?: array<string,string>}>
     */
    public function sheet(array $row): array
    {
        $lbl = fn (array $map, string $v) => ($map[$v] ?? '—').($v !== '' ? " ({$v})" : '');
        $simple = fn (string $n, string $title, string $value) => ['n' => $n, 'title' => $title, 'value' => $value !== '' ? $value : '—'];

        $items = [
            $simple('1', 'Tipo de notificação', $lbl(['1' => 'Negativa', '2' => 'Individual', '3' => 'Surto', '4' => 'Agregado'], $row['TP_NOT'])),
            $simple('2', 'Agravo (CID-10)', $row['ID_AGRAVO'].' — Violência interpessoal/autoprovocada'),
            $simple('3', 'Data da notificação', $row['DT_NOTIFIC']),
            $simple('4', 'UF de notificação', $row['SG_UF_NOT']),
            $simple('5', 'Município de notificação (IBGE)', $row['ID_MUNICIP']),
            $simple('6', 'Unidade notificadora', $lbl(['1' => 'Unidade de Saúde', '2' => 'Unidade de Assistência Social', '3' => 'Estabelecimento de Ensino',
                '4' => 'Conselho Tutelar', '5' => 'Unidade de Saúde Indígena', '6' => 'Centro Esp. de Atendimento à Mulher', '7' => 'Outros'], $row['TP_UNI_EXT'])),
            $simple('7', 'Nome / código da unidade', trim($row['NM_UNI_EXT'].' · '.$row['CO_UNI_EXT'], ' ·')),
            $simple('9', 'Data da ocorrência da violência', $row['DT_OCOR']),
            $simple('10', 'Nome do paciente', $row['NM_PACIENT']),
            $simple('11', 'Data de nascimento', $row['DT_NASC']),
            $simple('12', 'Idade (unidade+valor)', $row['NU_IDADE_N']),
            $simple('13', 'Sexo', $lbl(['M' => 'Masculino', 'F' => 'Feminino', 'I' => 'Ignorado'], $row['CS_SEXO'])),
            $simple('15', 'Raça/Cor', $lbl(['1' => 'Branca', '2' => 'Preta', '3' => 'Amarela', '4' => 'Parda', '5' => 'Indígena', '9' => 'Ignorado'], $row['CS_RACA'])),
            $simple('18', 'Nome da mãe', $row['NM_MAE_PA']),
            $simple('19', 'UF de residência', $row['SG_UF']),
            $simple('20', 'Município de residência (IBGE)', $row['ID_MN_RESI']),
            $simple('22', 'Bairro', $row['NM_BAIRRO']),
            $simple('23', 'Logradouro', $row['NM_LOGRAD']),
            $simple('40', 'UF de ocorrência', $row['SG_UF_OCOR']),
            $simple('41', 'Município de ocorrência (IBGE)', $row['ID_MN_OCOR']),
            $simple('50', 'Zona de ocorrência', $lbl(['1' => 'Urbana', '2' => 'Rural', '3' => 'Periurbana', '9' => 'Ignorado'], $row['ZONA_OCOR'])),
            $simple('52', 'Local de ocorrência', $lbl(['01' => 'Residência', '02' => 'Habitação coletiva', '03' => 'Escola', '05' => 'Bar ou similar', '06' => 'Via pública',
                '07' => 'Comércio/Serviços', '09' => 'Outros', '99' => 'Ignorado'], $row['LOCAL_OCOR']).($row['LOCAL_ESPE'] ? ' — '.$row['LOCAL_ESPE'] : '')),
            $simple('53', 'Ocorreu outras vezes?', $lbl(self::SIM_NAO_LABEL, $row['OUT_VEZES'])),
        ];

        $group = function (string $n) use ($row) {
            [$title, $cols] = self::GROUPS[$n];
            $out = [];
            foreach ($cols as $col => $label) {
                $out[$label] = (self::SIM_NAO_LABEL[$row[$col]] ?? '—')." ({$row[$col]})";
            }

            return ['n' => $n, 'title' => $title, 'group' => $out];
        };

        foreach (['56', '57', '58'] as $n) {
            $items[] = $group($n);
        }
        $items[] = $simple('60', 'Número de envolvidos', $lbl(['1' => 'Um', '2' => 'Dois ou mais', '9' => 'Ignorado'], $row['NUM_ENVOL']));
        $items[] = $group('61');
        $items[] = $simple('62', 'Sexo do provável autor', $lbl(['1' => 'Masculino', '2' => 'Feminino', '3' => 'Ambos os sexos', '9' => 'Ignorado'], $row['AUTOR_SEXO']));
        $items[] = $simple('63', 'Suspeita de uso de álcool', $lbl(self::SIM_NAO_LABEL, $row['AUTOR_ALCO']));
        $items[] = $simple('64', 'Ciclo de vida do provável autor', $lbl(['1' => 'Criança', '2' => 'Adolescente', '3' => 'Jovem', '4' => 'Pessoa adulta', '5' => 'Pessoa idosa', '9' => 'Ignorado'], $row['CICL_VID_AU']));
        $items[] = $group('65');

        return $items;
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
