<?php

namespace App\Services;

use App\Enums\CaseStatus;
use App\Enums\Role;
use App\Enums\SessionStatus;
use App\Models\CaseRecord;
use App\Models\ListeningSession;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Lista de espera: casos abertos que ainda não têm escuta marcada nem realizada.
 *
 * A ordem é explicável e sem pontuação oculta, nesta sequência:
 *  1. caso urgente (risco imediato);  2. violência sexual;  3. meta da 1ª escuta vencida;
 *  4. quem espera há mais tempo.
 */
class WaitingList
{
    public const FILTERS = [
        'todos' => 'Todos',
        'sem_responsavel' => 'Sem responsável',
        'sem_escuta' => 'Com responsável, sem escuta marcada',
        'atrasados' => 'Meta da 1ª escuta vencida',
    ];

    /** Casos que esperam atendimento, já na ordem de prioridade. */
    public function cases(User $user, string $filter = 'todos'): Collection
    {
        $cases = CaseRecord::visibleTo($user)
            ->with('child', 'responsible', 'municipality')
            ->whereNull('cases.anonymized_at')
            ->where('cases.status', '!=', CaseStatus::Encerrado)
            ->whereDoesntHave('sessions', fn (Builder $q) => $q->whereIn('status', [SessionStatus::Agendada, SessionStatus::Realizada]))
            ->when($filter === 'sem_responsavel', fn ($q) => $q->whereNull('cases.responsible_id'))
            ->when($filter === 'sem_escuta', fn ($q) => $q->whereNotNull('cases.responsible_id'))
            ->get();

        if ($filter === 'atrasados') {
            $cases = $cases->filter(fn (CaseRecord $c) => $this->overdue($c));
        }

        return $cases->sort(fn (CaseRecord $a, CaseRecord $b) => [
            (int) $b->urgent, (int) $this->sexual($b), (int) $this->overdue($b), $a->received_at->timestamp, $a->id,
        ] <=> [
            (int) $a->urgent, (int) $this->sexual($a), (int) $this->overdue($a), $b->received_at->timestamp, $b->id,
        ])->values();
    }

    public function sexual(CaseRecord $c): bool
    {
        return in_array('sexual', $c->violence_types ?? [], true);
    }

    /** Data limite para a 1ª escuta, pela meta do município. */
    public function dueDate(CaseRecord $c): CarbonInterface
    {
        return $c->received_at->copy()->addDays($c->municipality->first_listening_days);
    }

    public function overdue(CaseRecord $c): bool
    {
        return $this->dueDate($c)->startOfDay()->lt(today());
    }

    public function waitingDays(CaseRecord $c): int
    {
        return (int) floor(abs($c->received_at->copy()->startOfDay()->diffInDays(today())));
    }

    /** @return list<string> motivos legíveis da posição na fila */
    public function reasons(CaseRecord $c): array
    {
        return array_values(array_filter([
            $c->urgent ? 'Urgente' : null,
            $this->sexual($c) ? 'Violência sexual' : null,
            $this->overdue($c) ? 'Meta vencida' : null,
            $c->source === 'publica' ? 'Denúncia pública' : null,
        ]));
    }

    /**
     * Carga de cada profissional do município, para a coordenação distribuir bem.
     *
     * @return Collection<int, array{user: User, open: int, waiting: int, upcoming: int}>
     */
    public function workload(int $municipalityId): Collection
    {
        $pros = User::where('municipality_id', $municipalityId)->where('active', true)
            ->whereIn('role', [Role::Psicologo->value, Role::AssistenteSocial->value])->orderBy('name')->get();

        $open = CaseRecord::where('municipality_id', $municipalityId)->whereNotNull('responsible_id')
            ->whereNull('anonymized_at')->where('status', '!=', CaseStatus::Encerrado)
            ->selectRaw('responsible_id, count(*) as n')->groupBy('responsible_id')->pluck('n', 'responsible_id');

        $waiting = CaseRecord::where('municipality_id', $municipalityId)->whereNotNull('responsible_id')
            ->whereNull('anonymized_at')->where('status', '!=', CaseStatus::Encerrado)
            ->whereDoesntHave('sessions', fn (Builder $q) => $q->whereIn('status', [SessionStatus::Agendada, SessionStatus::Realizada]))
            ->selectRaw('responsible_id, count(*) as n')->groupBy('responsible_id')->pluck('n', 'responsible_id');

        $upcoming = ListeningSession::whereIn('professional_id', $pros->pluck('id'))->where('status', SessionStatus::Agendada)
            ->whereBetween('scheduled_at', [now(), now()->addDays(7)])
            ->selectRaw('professional_id, count(*) as n')->groupBy('professional_id')->pluck('n', 'professional_id');

        return $pros->map(fn (User $u) => [
            'user' => $u, 'open' => (int) ($open[$u->id] ?? 0), 'waiting' => (int) ($waiting[$u->id] ?? 0), 'upcoming' => (int) ($upcoming[$u->id] ?? 0),
        ]);
    }
}
