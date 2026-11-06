<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * Ficha de notificação de violência (inspirada na Ficha SINAN de Violência
 * Interpessoal/Autoprovocada). Centraliza opções, rótulos e validação para que
 * o formulário interno e o público usem exatamente os mesmos campos.
 */
class Ficha
{
    public const OPTIONS = [
        'local_ocorrencia' => ['residencia' => 'Residência', 'escola' => 'Escola', 'habitacao_coletiva' => 'Habitação coletiva',
            'via_publica' => 'Via pública', 'bar' => 'Bar ou similar', 'comercio' => 'Comércio / serviços',
            'internet' => 'Ambiente virtual / internet', 'outro' => 'Outro', 'ignorado' => 'Ignorado'],
        'zona' => ['urbana' => 'Urbana', 'rural' => 'Rural', 'periurbana' => 'Periurbana', 'ignorado' => 'Ignorado'],
        'ocorreu_outras_vezes' => ['sim' => 'Sim', 'nao' => 'Não', 'ignorado' => 'Ignorado'],
        'meio_agressao' => ['forca_corporal' => 'Força corporal / espancamento', 'objeto_contundente' => 'Objeto contundente',
            'perfurocortante' => 'Objeto perfurocortante', 'arma_fogo' => 'Arma de fogo', 'ameaca' => 'Ameaça',
            'enforcamento' => 'Enforcamento / sufocação', 'envenenamento' => 'Envenenamento', 'queimadura' => 'Substância quente / queimadura',
            'outro' => 'Outro'],
        'tipo_sexual' => ['assedio' => 'Assédio sexual', 'estupro' => 'Estupro', 'pornografia_infantil' => 'Pornografia infantil',
            'exploracao_sexual' => 'Exploração sexual', 'atentado_pudor' => 'Atentado violento ao pudor', 'outro' => 'Outro'],
        // Categorias do campo 61 do SINAN (relação do provável autor com a pessoa atendida).
        'vinculo_autor' => ['pai' => 'Pai', 'mae' => 'Mãe', 'padrasto' => 'Padrasto', 'madrasta' => 'Madrasta',
            'conjuge' => 'Cônjuge', 'ex_conjuge' => 'Ex-cônjuge', 'namorado' => 'Namorado(a)', 'ex_namorado' => 'Ex-namorado(a)',
            'filho' => 'Filho(a)', 'irmao' => 'Irmão(ã)', 'amigo_conhecido' => 'Amigo / conhecido', 'desconhecido' => 'Desconhecido',
            'cuidador' => 'Cuidador(a)', 'patrao_chefe' => 'Patrão / chefe', 'professor_instituicao' => 'Pessoa com relação institucional',
            'policial' => 'Policial / agente da lei', 'proprietario_estabelecimento' => 'Proprietário de estabelecimento',
            'outro_familiar' => 'Outro familiar', 'outro' => 'Outro'],
        'sexo_autor' => ['masculino' => 'Masculino', 'feminino' => 'Feminino', 'ambos' => 'Ambos', 'ignorado' => 'Ignorado'],
        // Campo 64 do SINAN (ciclo de vida do provável autor).
        'faixa_autor' => ['crianca' => 'Criança', 'adolescente' => 'Adolescente', 'jovem' => 'Jovem', 'adulta' => 'Pessoa adulta',
            'idosa' => 'Pessoa idosa', 'ignorado' => 'Ignorado'],
        'suspeita_alcool' => ['sim' => 'Sim', 'nao' => 'Não', 'ignorado' => 'Ignorado'],
        'num_envolvidos' => ['um' => 'Um', 'dois_mais' => 'Dois ou mais', 'ignorado' => 'Ignorado'],
        'raca_cor' => ['branca' => 'Branca', 'preta' => 'Preta', 'amarela' => 'Amarela', 'parda' => 'Parda', 'indigena' => 'Indígena', 'ignorado' => 'Ignorado'],
        'vinculo_notificante' => ['familiar' => 'Familiar', 'vizinho' => 'Vizinho / conhecido', 'escola' => 'Escola / educador', 'saude' => 'Profissional de saúde',
            'assistencia' => 'Assistência social', 'a_propria_vitima' => 'A própria criança/adolescente', 'outro' => 'Outro'],
    ];

    /** Rótulos das seções do formulário. */
    public const LABELS = [
        'local_ocorrencia' => 'Local da ocorrência', 'zona' => 'Zona da ocorrência', 'ocorreu_outras_vezes' => 'Ocorreu outras vezes?',
        'meio_agressao' => 'Meio de agressão', 'tipo_sexual' => 'Tipo de violência sexual', 'vinculo_autor' => 'Vínculo do provável autor',
        'sexo_autor' => 'Sexo do provável autor', 'faixa_autor' => 'Ciclo de vida do provável autor', 'suspeita_alcool' => 'Suspeita de uso de álcool',
        'num_envolvidos' => 'Nº de envolvidos', 'raca_cor' => 'Raça/cor da criança ou adolescente', 'vinculo_notificante' => 'Vínculo do notificante',
    ];

    public static function options(string $field): array
    {
        return self::OPTIONS[$field];
    }

    public static function label(string $field, ?string $value): string
    {
        return $value ? (self::OPTIONS[$field][$value] ?? $value) : '—';
    }

    /** Regras de validação para os campos dentro do prefixo informado (ex.: 'ficha'). */
    public static function rules(string $prefix = 'ficha'): array
    {
        $rules = [];
        foreach (self::OPTIONS as $field => $opts) {
            $rules["$prefix.$field"] = ['nullable', Rule::in(array_keys($opts))];
        }
        $rules["$prefix.meio_agressao"] = ['nullable', 'array'];
        $rules["$prefix.meio_agressao.*"] = [Rule::in(array_keys(self::OPTIONS['meio_agressao']))];
        $rules["$prefix.tipo_sexual"] = ['nullable', 'array'];
        $rules["$prefix.tipo_sexual.*"] = [Rule::in(array_keys(self::OPTIONS['tipo_sexual']))];
        $rules["$prefix.local_outro"] = ['nullable', 'string', 'max:255'];
        $rules["$prefix.notificante_nome"] = ['nullable', 'string', 'max:255'];
        $rules["$prefix.notificante_telefone"] = ['nullable', 'string', 'max:30'];
        $rules["$prefix.notificante_email"] = ['nullable', 'email', 'max:255'];
        $rules["$prefix.notificante_anonimo"] = ['boolean'];
        $rules["$prefix.autor_descricao"] = ['nullable', 'string', 'max:1000'];

        return $rules;
    }

    /** Remove vazios e normaliza booleanos antes de salvar. */
    public static function clean(array $ficha): array
    {
        $ficha['notificante_anonimo'] = (bool) ($ficha['notificante_anonimo'] ?? false);

        return array_filter($ficha, fn ($v) => $v !== null && $v !== '' && $v !== []);
    }
}
