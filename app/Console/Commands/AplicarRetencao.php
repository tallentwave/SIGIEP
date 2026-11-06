<?php

namespace App\Console\Commands;

use App\Enums\AuditAction;
use App\Models\Municipality;
use App\Services\Anonymizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('sigiep:retencao {--dry-run : Só lista o que seria anonimizado} {--municipio= : Limita a um município (id)}')]
#[Description('Anonimiza casos com prazo de guarda vencido (sem guarda legal) e expurga trilha de auditoria antiga')]
class AplicarRetencao extends Command
{
    public function handle(Anonymizer $anonymizer): int
    {
        $dry = $this->option('dry-run');
        $total = 0;

        foreach (Municipality::query()->when($this->option('municipio'), fn ($q, $id) => $q->whereKey($id))->get() as $m) {
            $cases = $anonymizer->eligible($m->id)->with('municipality')->get();
            $this->line("{$m->fullName()}: {$cases->count()} caso(s) elegível(is) (prazo {$m->retention_years} anos).");

            foreach ($cases as $case) {
                $this->line(" - {$case->protocol}".($dry ? ' [simulação]' : ''));
                if (! $dry) {
                    $reason = $case->deleted_at ? 'exclusao' : 'prazo';
                    $total += (int) $anonymizer->anonymize($case, $reason);
                }
            }
        }

        // A própria trilha de auditoria tem prazo (não guarda conteúdo, mas guarda IP e nomes).
        $years = (int) config('sigiep.audit_retention_years');
        if ($years > 0) {
            $cutoff = now()->subYears($years);
            $old = DB::table('audit_logs')->where('created_at', '<', $cutoff)->count();
            $this->line("Auditoria anterior a {$cutoff->format('d/m/Y')}: {$old} registro(s).");
            if (! $dry && $old > 0) {
                DB::table('audit_logs')->where('created_at', '<', $cutoff)->delete();
                DB::table('audit_logs')->insert(['action' => AuditAction::AuditPruned->value, 'user_name' => 'sistema (retenção)',
                    'changes' => json_encode(['removidos' => $old, 'anteriores_a' => $cutoff->toDateString()]), 'created_at' => now()]);
            }
        }

        $this->info($dry ? 'Simulação concluída: nada foi alterado.' : "Casos anonimizados: {$total}");

        return self::SUCCESS;
    }
}
