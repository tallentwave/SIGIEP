<?php

namespace App\Console\Commands;

use App\Enums\CaseStatus;
use App\Enums\ReferralStatus;
use App\Enums\Role;
use App\Enums\SessionStatus;
use App\Models\CaseRecord;
use App\Models\ListeningSession;
use App\Models\Municipality;
use App\Models\Referral;
use App\Models\User;
use App\Notifications\AlertasDiarios;
use App\Services\SinanExporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sigiep:alertas')]
#[Description('Envia e-mail com encaminhamentos atrasados, escutas das próximas 24h, casos parados na fila e SINAN vencido (prazos por município)')]
class EnviarAlertas extends Command
{
    public function handle(): int
    {
        $sent = 0;

        foreach (Municipality::where('active', true)->get() as $m) {
            $cases = CaseRecord::where('municipality_id', $m->id);

            $atrasados = Referral::whereIn('case_id', (clone $cases)->select('id'))
                ->whereNotIn('status', [ReferralStatus::ComRetorno->value, ReferralStatus::Pendente->value])
                ->whereDate('due_at', '<', now())->with('case')->get();

            $sessions = ListeningSession::whereIn('case_id', (clone $cases)->select('id'))
                ->where('status', SessionStatus::Agendada)
                ->whereBetween('scheduled_at', [now(), now()->addDay()])->with('case')->get();

            $fila = (clone $cases)->whereNull('responsible_id')->where('status', '!=', CaseStatus::Encerrado)
                ->whereDate('received_at', '<=', now()->subDays($m->queue_alert_days))->get();

            $sinan = (clone $cases)->where('condition', 'vitima')->whereNull('anonymized_at')
                ->whereDoesntHave('dispatches', fn ($q) => $q->where('system', 'sinan')->whereIn('status', ['concluido', 'dispensado']))
                ->with('municipality')->get()->filter(fn ($c) => SinanExporter::deadline($c)->isPast());

            if ($atrasados->isEmpty() && $sessions->isEmpty() && $fila->isEmpty() && $sinan->isEmpty()) {
                continue;
            }

            $dados = [
                'atrasados' => $atrasados->pluck('case.protocol')->unique()->values()->all(),
                'escutas' => $sessions->pluck('case.protocol')->unique()->values()->all(),
                'fila' => $fila->pluck('protocol')->all(),
                'sinan' => $sinan->pluck('protocol')->all(),
            ];

            // Coordenação recebe tudo; profissionais, só o que é dos seus casos.
            $coord = User::where('municipality_id', $m->id)->where('active', true)->where('role', Role::Coordenacao->value)->get();
            foreach ($coord as $u) {
                $u->notify(new AlertasDiarios($dados));
                $sent++;
            }

            $profissionais = $sessions->groupBy('professional_id');
            foreach ($profissionais as $userId => $list) {
                $user = User::where('active', true)->find($userId);
                if ($user && ! $user->isCoordination()) {
                    $user->notify(new AlertasDiarios(['atrasados' => [], 'fila' => [],
                        'escutas' => $list->pluck('case.protocol')->unique()->values()->all()]));
                    $sent++;
                }
            }
        }

        $this->info("Alertas enviados: {$sent}");

        return self::SUCCESS;
    }
}
