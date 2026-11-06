<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\CaseStatus;
use App\Enums\ReferralStatus;
use App\Enums\SessionStatus;
use App\Enums\ViolenceType;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\ListeningSession;
use App\Models\Municipality;
use App\Models\Referral;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Indicadores agregados: nunca expõem nomes, relatos ou fichas individuais. */
class StatsController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewStats');

        return view('relatorios.estatisticas', $this->data($request) + [
            'municipalities' => $request->user()->isAdmin() ? Municipality::orderBy('name')->get() : collect(),
        ]);
    }

    public function pdf(Request $request)
    {
        $this->authorize('viewStats');
        AuditLog::record(AuditAction::Exported, null, null, ['report' => 'estatisticas']);

        return Pdf::loadView('relatorios.estatisticas_pdf', $this->data($request))
            ->download('sigiep-estatisticas-'.now()->format('Ymd').'.pdf');
    }

    public function data(Request $request): array
    {
        $user = $request->user();
        $from = $request->filled('de') ? Carbon::parse($request->query('de'))->startOfDay() : now()->subMonths(11)->startOfMonth();
        $to = $request->filled('ate') ? Carbon::parse($request->query('ate'))->endOfDay() : now()->endOfDay();

        $municipalityId = $user->isAdmin() ? ($request->integer('municipio') ?: null) : $user->municipality_id;

        $cases = CaseRecord::query()
            ->when($municipalityId, fn ($q) => $q->where('municipality_id', $municipalityId))
            // whereDate: a coluna é gravada com hora; comparar só a data inclui o último dia do período.
            ->whereDate('received_at', '>=', $from->toDateString())
            ->whereDate('received_at', '<=', $to->toDateString());
        $ids = (clone $cases)->select('id');

        $list = (clone $cases)->with('child:id,birth_date')->get(['id', 'child_id', 'status', 'violence_types', 'condition', 'origin', 'urgent', 'received_at', 'source']);

        $byType = array_fill_keys(array_map(fn ($t) => $t->value, ViolenceType::cases()), 0);
        $byAge = ['0–5' => 0, '6–11' => 0, '12–14' => 0, '15–17' => 0, 'Não informada' => 0];
        $byMonth = [];
        for ($d = $from->copy()->startOfMonth(); $d <= $to; $d->addMonth()) {
            $byMonth[$d->format('m/Y')] = 0;
        }
        $byOrigin = [];

        foreach ($list as $c) {
            foreach ($c->violence_types ?? [] as $t) {
                $byType[$t] = ($byType[$t] ?? 0) + 1;
            }
            // Carbon 3 devolve decimal: sem o floor, 5,5 anos cairia na faixa 6–11.
            $age = $c->child->birth_date ? (int) floor(abs($c->child->birth_date->diffInYears($c->received_at))) : null;
            $byAge[match (true) {
                $age === null => 'Não informada', $age <= 5 => '0–5', $age <= 11 => '6–11', $age <= 14 => '12–14', default => '15–17',
            }]++;
            $byMonth[$c->received_at->format('m/Y')] = ($byMonth[$c->received_at->format('m/Y')] ?? 0) + 1;
            $byOrigin[$c->origin] = ($byOrigin[$c->origin] ?? 0) + 1;
        }
        arsort($byOrigin);

        $statusCounts = $list->groupBy(fn ($c) => $c->status->value)->map->count();

        // Tempo até a primeira escuta realizada.
        $firsts = ListeningSession::whereIn('case_id', $ids)->where('status', SessionStatus::Realizada)
            ->selectRaw('case_id, MIN(performed_at) as first_at')->groupBy('case_id')->pluck('first_at', 'case_id');
        $days = $list->filter(fn ($c) => isset($firsts[$c->id]))
            ->map(fn ($c) => $c->received_at->diffInDays(Carbon::parse($firsts[$c->id])));

        $refs = Referral::whereIn('case_id', $ids)->get(['status', 'due_at', 'sent_at', 'returned_at']);

        return [
            'from' => $from, 'to' => $to, 'municipalityId' => $municipalityId,
            'municipalityName' => $municipalityId ? Municipality::find($municipalityId)?->fullName() : 'Todos os municípios',
            'total' => $list->count(), 'urgent' => $list->where('urgent', true)->count(),
            'publicas' => $list->where('source', 'publica')->count(),
            'byType' => collect($byType)->filter()->mapWithKeys(fn ($n, $k) => [ViolenceType::from($k)->label() => $n])->all(),
            'byAge' => $byAge, 'byMonth' => $byMonth, 'byOrigin' => array_slice($byOrigin, 0, 8, true),
            'byStatus' => collect(CaseStatus::cases())->mapWithKeys(fn ($s) => [$s->label() => $statusCounts[$s->value] ?? 0])->all(),
            'avgDaysToFirst' => $days->isEmpty() ? null : round($days->avg(), 1),
            'withoutListening' => $list->count() - $days->count(),
            'refTotal' => $refs->count(),
            'refReturned' => $refs->where('status', ReferralStatus::ComRetorno)->count(),
            'refOverdue' => $refs->filter->isOverdue()->count(),
        ];
    }
}
