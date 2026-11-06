<?php

namespace App\Support;

use App\Enums\Condition;
use App\Enums\ViolenceType;
use Illuminate\Validation\Rule;

/**
 * Campos e validação do cadastro de caso, compartilhados pelo formulário
 * interno e pelo público para que nunca divirjam. A diferença do público é
 * só o que o servidor define sozinho (órgão, data de recebimento) e a
 * obrigatoriedade do nome (quem denuncia pode não saber).
 */
class CaseInput
{
    public static function rules(bool $public = false): array
    {
        return [
            'child.name' => [$public ? 'nullable' : 'required', 'string', 'max:255'],
            'child.social_name' => ['nullable', 'string', 'max:255'],
            'child.birth_date' => ['nullable', 'date', 'before_or_equal:today', 'after:'.now()->subYears(19)->toDateString()],
            'child.birth_date_estimated' => ['boolean'],
            'child.sex' => ['nullable', 'string', 'max:20'],
            'child.cpf' => ['nullable', 'string', 'max:14'],
            'child.mother_name' => ['nullable', 'string', 'max:255'],
            'child.school' => ['nullable', 'string', 'max:255'],
            'child.address' => ['nullable', 'string', 'max:255'],
            'child.neighborhood' => ['nullable', 'string', 'max:255'],
            'child.city' => ['nullable', 'string', 'max:255'],
            'child.has_disability' => ['boolean'],
            'child.disability_notes' => ['nullable', 'string', 'max:255'],
            'guardians' => ['array', 'max:6'],
            'guardians.*.name' => ['nullable', 'string', 'max:255'],
            'guardians.*.relationship' => ['nullable', 'string', 'max:100'],
            'guardians.*.phone' => ['nullable', 'string', 'max:30'],
            'guardians.*.cpf' => ['nullable', 'string', 'max:14'],
            'guardians.*.is_suspected' => ['boolean'],
            'guardians.*.can_be_contacted' => ['boolean'],
            'case.condition' => ['required', Rule::enum(Condition::class)],
            'case.violence_types' => ['required', 'array', 'min:1'],
            'case.violence_types.*' => [Rule::enum(ViolenceType::class)],
            'case.violence_date' => ['nullable', 'date', 'before_or_equal:today'],
            'case.alleged_author' => ['nullable', 'string', 'max:255'],
            'case.urgent' => ['boolean'],
            ...($public ? [
                'case.initial_report' => ['required', 'string', 'min:10', 'max:20000'],
            ] : [
                'case.origin' => ['required', 'string', 'max:255'],
                'case.origin_document' => ['nullable', 'string', 'max:255'],
                'case.received_at' => ['required', 'date', 'before_or_equal:today'],
                'case.initial_report' => ['nullable', 'string', 'max:20000'],
            ]),
            ...Ficha::rules('ficha'),
        ];
    }

    public static function attributes(): array
    {
        return [
            'child.name' => 'nome da criança/adolescente',
            'child.birth_date' => 'data de nascimento',
            'case.condition' => 'condição',
            'case.violence_types' => 'tipo de violência',
            'case.origin' => 'órgão demandante',
            'case.received_at' => 'data de recebimento',
            'case.initial_report' => 'descrição dos fatos',
        ];
    }

    /** Descarta responsáveis em branco, normaliza checkboxes e limpa a ficha. */
    public static function normalize(array $v): array
    {
        $v['guardians'] = collect($v['guardians'] ?? [])
            ->filter(fn ($g) => filled($g['name'] ?? null))
            ->map(fn ($g) => [...$g,
                'is_suspected' => (bool) ($g['is_suspected'] ?? false),
                'can_be_contacted' => (bool) ($g['can_be_contacted'] ?? false),
            ])->values()->all();

        $v['child']['birth_date_estimated'] = (bool) ($v['child']['birth_date_estimated'] ?? false);
        $v['child']['has_disability'] = (bool) ($v['child']['has_disability'] ?? false);
        $v['case']['urgent'] = (bool) ($v['case']['urgent'] ?? false);
        $v['ficha'] = Ficha::clean($v['ficha'] ?? []);

        return $v;
    }
}
