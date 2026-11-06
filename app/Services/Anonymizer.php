<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\CaseStatus;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\Child;
use App\Support\Ficha;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Anonimização irreversível de casos cujo prazo de guarda venceu.
 *
 * Remove tudo que identifica a criança/família ou conta a história (nomes,
 * documentos, endereços, relatos, textos livres), e MANTÉM o que serve a
 * estatística e à prestação de contas (tipo de violência, datas, município,
 * faixa etária pelo ano de nascimento, categorias da ficha, encaminhamentos).
 */
class Anonymizer
{
    public const SOFT_DELETE_GRACE_DAYS = 90;

    /** Casos elegíveis à anonimização automática, sem guarda legal. */
    public function eligible(?int $municipalityId = null): Builder
    {
        return CaseRecord::withTrashed()
            ->whereNull('cases.anonymized_at')
            ->where('cases.legal_hold', false)
            ->when($municipalityId, fn ($q) => $q->where('cases.municipality_id', $municipalityId))
            ->where(function (Builder $q) {
                // Encerrado há mais que o prazo do município…
                $q->where(fn (Builder $w) => $w->whereNotNull('cases.closed_at')
                    ->whereRaw($this->dueExpression().' <= ?', [now()->toDateTimeString()]))
                  // …ou excluído (lixeira) há mais de 90 dias: não deve ficar com conteúdo para sempre.
                    ->orWhere(fn (Builder $w) => $w->whereNotNull('cases.deleted_at')
                        ->where('cases.deleted_at', '<=', now()->subDays(self::SOFT_DELETE_GRACE_DAYS)));
            });
    }

    /** Casos que vencem nos próximos N dias (para a coordenação se preparar / aplicar guarda legal). */
    public function dueSoon(int $municipalityId, int $days = 90): Builder
    {
        return CaseRecord::query()
            ->where('cases.municipality_id', $municipalityId)
            ->whereNull('cases.anonymized_at')->whereNotNull('cases.closed_at')
            ->whereRaw($this->dueExpression().' > ?', [now()->toDateTimeString()])
            ->whereRaw($this->dueExpression().' <= ?', [now()->addDays($days)->toDateTimeString()]);
    }

    public function dueDate(CaseRecord $case): ?CarbonInterface
    {
        return $case->closed_at?->copy()->addYears($case->municipality->retention_years);
    }

    /** Anonimiza um caso. Devolve false se bloqueado por guarda legal ou se já anonimizado. */
    public function anonymize(CaseRecord $case, string $reason, ?int $byUserId = null): bool
    {
        if ($case->legal_hold || $case->isAnonymized()) {
            return false;
        }

        DB::transaction(function () use ($case, $reason) {
            $now = now();

            // Casos
            $case->forceFill([
                'initial_report' => null,
                'alleged_author' => null,
                'origin_document' => null,
                'ficha' => $this->scrubFicha($case->ficha ?? []),
                'anonymized_at' => $now,
                'anonymization_reason' => $reason,
                'legal_hold_reason' => null,
            ])->saveQuietly();

            foreach ($case->sessions()->withTrashed()->get() as $s) {
                $s->forceFill(['report' => null, 'technical_notes' => null, 'accompanied_by' => null, 'room' => null])->saveQuietly();
            }
            foreach ($case->referrals()->withTrashed()->get() as $r) {
                $r->forceFill(['summary' => null, 'return_notes' => null, 'document_number' => null])->saveQuietly();
            }
            foreach ($case->dispatches()->get() as $d) {
                $d->forceFill(['summary' => null, 'reference' => null])->saveQuietly();
            }
            // Nº do processo permite reidentificar a criança pelo Judiciário: some junto.
            $case->processes()->delete();

            // A criança só é anonimizada quando TODOS os seus casos já foram.
            $child = Child::withTrashed()->find($case->child_id);
            if ($child && ! $child->anonymized_at) {
                $pending = CaseRecord::withTrashed()->where('child_id', $child->id)->whereNull('anonymized_at')->exists();
                if (! $pending) {
                    $this->anonymizeChild($child, $now);
                }
            }
        });

        // Registro de auditoria sem conteúdo: quem/quando/por quê.
        AuditLog::create([
            'user_id' => $byUserId, 'user_name' => $byUserId ? null : 'sistema (retenção)',
            'action' => AuditAction::Anonymized, 'subject_type' => $case->getMorphClass(), 'subject_id' => $case->id,
            'case_id' => $case->id, 'municipality_id' => $case->municipality_id, 'changes' => ['reason' => $reason],
        ]);

        return true;
    }

    private function anonymizeChild(Child $child, $now): void
    {
        $child->guardians()->delete(); // dado de terceiros: remoção definitiva
        $child->forceFill([
            'name' => 'Anonimizado', 'social_name' => null, 'cpf' => null, 'mother_name' => null,
            'school' => null, 'address' => null, 'neighborhood' => null, 'disability_notes' => null,
            // Mantém só o ano de nascimento (faixa etária nas estatísticas).
            'birth_date' => $child->birth_date ? $child->birth_date->copy()->startOfYear() : null,
            'birth_date_estimated' => $child->birth_date !== null,
            'anonymized_at' => $now,
        ])->saveQuietly();
    }

    /** Mantém só as categorias da ficha; descarta texto livre e dados do notificante. */
    private function scrubFicha(array $ficha): array
    {
        $keep = array_keys(Ficha::OPTIONS);
        unset($keep[array_search('vinculo_notificante', $keep, true)]);

        return array_intersect_key($ficha, array_flip($keep));
    }

    /** Expressão SQL "fechado em + prazo do município" compatível com SQLite/MySQL/PostgreSQL. */
    private function dueExpression(): string
    {
        $driver = DB::connection()->getDriverName();
        $years = '(select retention_years from municipalities where municipalities.id = cases.municipality_id)';

        return match ($driver) {
            'sqlite' => "datetime(cases.closed_at, '+' || {$years} || ' years')",
            'pgsql' => "(cases.closed_at + ({$years} || ' years')::interval)",
            default => "DATE_ADD(cases.closed_at, INTERVAL {$years} YEAR)",
        };
    }

    public static function isFinal(CaseRecord $case): bool
    {
        return $case->status === CaseStatus::Encerrado;
    }
}
