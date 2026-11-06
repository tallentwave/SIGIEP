<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\DispatchStatus;
use App\Enums\DispatchSystem;
use App\Enums\ViolenceType;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\IntegrationDispatch;
use App\Models\JudicialProcess;
use App\Services\SinanExporter;
use App\Support\CnjNumber;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * SINAN: gera o arquivo para a vigilância epidemiológica e controla o nº de notificação devolvido.
 * Justiça: vincula processos (numeração única CNJ validada) e gera a comunicação ao Juízo.
 * Nenhuma chamada externa é feita: não há API pública do SINAN, e o MNI do CNJ exige adesão do tribunal.
 */
class IntegrationController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', CaseRecord::class);
        abort_unless($request->user()->isCoordination(), 403);

        $cases = CaseRecord::visibleTo($request->user())->with('child', 'municipality', 'dispatches')
            ->where('condition', 'vitima')->whereNull('anonymized_at')->get()
            ->filter(fn ($c) => ! $c->dispatches->where('system', DispatchSystem::Sinan)
                ->whereIn('status', [DispatchStatus::Concluido, DispatchStatus::Dispensado])->count());

        $pending = $cases->sortBy(fn ($c) => SinanExporter::deadline($c)->timestamp)->values();
        $waiting = IntegrationDispatch::with('case')->where('system', 'sinan')->where('status', DispatchStatus::Gerado)
            ->whereIn('case_id', CaseRecord::visibleTo($request->user())->select('cases.id'))->latest()->get();

        return view('integracoes.index', [
            'pending' => $pending, 'waiting' => $waiting, 'exporter' => new SinanExporter,
            'municipality' => $request->user()->municipality,
        ]);
    }

    // ------------------------------- SINAN -------------------------------

    public function sinanFile(Request $request, SinanExporter $exporter)
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:200'], 'ids.*' => ['integer']]);
        $cases = CaseRecord::whereIn('id', $data['ids'])->with('child', 'municipality', 'referrals')->get()
            ->filter(fn ($c) => $request->user()->can('readReports', $c));
        abort_if($cases->isEmpty(), 403);

        $rows = [];
        $problems = [];
        $batch = (string) Str::ulid();
        foreach ($cases as $case) {
            $m = $exporter->map($case);
            if ($m['errors']) {
                $problems[] = $case->protocol.': '.implode(' ', $m['errors']);

                continue;
            }
            $rows[] = $m['row'];
            $case->dispatches()->create([
                'system' => DispatchSystem::Sinan, 'status' => DispatchStatus::Gerado, 'batch' => $batch,
                'payload_hash' => hash('sha256', json_encode($m['row'])), 'created_by' => $request->user()->id,
            ]);
            AuditLog::record(AuditAction::IntegrationGenerated, $case, $case->id, ['system' => 'sinan', 'batch' => $batch]);
        }

        if (! $rows) {
            return back()->withErrors($problems);
        }
        if ($problems) {
            session()->flash('ok', 'Arquivo gerado sem os casos com pendências: '.implode(' | ', $problems));
        }

        return response($exporter->csv($rows), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="sinan-violencia-'.now()->format('Ymd-His').'.csv"',
        ]);
    }

    /** Pré-visualização do que será enviado: avisos e pendências, sem gerar arquivo. */
    public function sinanPreview(Request $request, CaseRecord $case, SinanExporter $exporter)
    {
        $this->authorize('readReports', $case);

        return response()->json($exporter->map($case) + ['deadline' => SinanExporter::deadline($case)->toDateString()]);
    }

    public function sinanWaive(Request $request, CaseRecord $case)
    {
        $this->authorize('readReports', $case);
        abort_if($case->isAnonymized(), 403, 'Caso anonimizado.');
        $d = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']], [], ['reason' => 'justificativa']);

        $case->dispatches()->create([
            'system' => DispatchSystem::Sinan, 'status' => DispatchStatus::Dispensado,
            'summary' => $d['reason'], 'created_by' => $request->user()->id,
        ]);
        AuditLog::record(AuditAction::IntegrationWaived, $case, $case->id, ['system' => 'sinan']);

        return back()->with('ok', 'Notificação ao SINAN dispensada (justificativa registrada).');
    }

    /** Registra o retorno: nº de notificação do SINAN ou nº do ofício/protocolo enviado à Justiça. */
    public function confirm(Request $request, IntegrationDispatch $dispatch)
    {
        $this->authorize('readReports', $dispatch->case);
        abort_unless($dispatch->status === DispatchStatus::Gerado, 422, 'Este registro não está aguardando confirmação.');

        $d = $request->validate([
            'reference' => ['required', 'string', 'max:60', $dispatch->system === DispatchSystem::Sinan ? 'regex:/^\d{1,7}$/' : 'string'],
            'sent_at' => ['required', 'date', 'before_or_equal:today'],
        ], ['reference.regex' => 'O nº de notificação do SINAN tem até 7 dígitos numéricos.'],
            ['reference' => $dispatch->system === DispatchSystem::Sinan ? 'nº da notificação (NU_NOTIFIC)' : 'nº do ofício/protocolo', 'sent_at' => 'data']);

        $dispatch->update([...$d, 'status' => DispatchStatus::Concluido]);
        AuditLog::record(AuditAction::IntegrationConfirmed, $dispatch->case, $dispatch->case_id, ['system' => $dispatch->system->value]);

        return back()->with('ok', 'Confirmação registrada.');
    }

    // --------------------------- Sistema de Justiça ---------------------------

    public function storeProcess(Request $request, CaseRecord $case)
    {
        $this->authorize('readReports', $case);
        abort_if($case->isAnonymized(), 403, 'Caso anonimizado.');
        $d = $request->validate([
            'number' => ['required', 'string', 'max:30'],
            'court_unit' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [], ['number' => 'nº do processo']);

        $formatted = CnjNumber::format($d['number']);
        if (! $formatted) {
            return back()->withErrors(['number' => 'Numeração inválida: confira os 20 dígitos (NNNNNNN-DD.AAAA.J.TR.OOOO); o dígito verificador não confere.'])->withInput();
        }
        if ($case->processes()->where('number', $formatted)->exists()) {
            return back()->withErrors(['number' => 'Este processo já está vinculado ao caso.']);
        }

        $case->processes()->create([...$d, 'number' => $formatted, 'created_by' => $request->user()->id]);

        return back()->with('ok', 'Processo vinculado ('.CnjNumber::court($formatted).').');
    }

    public function destroyProcess(JudicialProcess $process)
    {
        $this->authorize('readReports', $process->case);
        $process->delete();

        return back()->with('ok', 'Vínculo com o processo removido.');
    }

    /** Comunicação ao Juízo: o MÍNIMO necessário. Não inclui o relato da escuta nem observações técnicas. */
    public function justicePdf(Request $request, CaseRecord $case)
    {
        $this->authorize('readReports', $case);
        abort_if($case->isAnonymized(), 403, 'Caso anonimizado.');
        $d = $request->validate([
            'summary' => ['required', 'string', 'min:20', 'max:4000'],
            'judicial_process_id' => ['nullable', Rule::exists('judicial_processes', 'id')->where('case_id', $case->id)],
            'recipient' => ['nullable', 'string', 'max:255'],
        ], [], ['summary' => 'síntese']);

        $case->load('child', 'municipality', 'referrals', 'sessions.professional', 'responsible');
        $process = isset($d['judicial_process_id']) ? $case->processes()->find($d['judicial_process_id']) : null;

        $dispatch = $case->dispatches()->create([
            'system' => DispatchSystem::Justica, 'status' => DispatchStatus::Gerado, 'judicial_process_id' => $process?->id,
            'summary' => $d['summary'], 'created_by' => $request->user()->id,
            'payload_hash' => hash('sha256', $case->protocol.'|'.$d['summary'].'|'.($process?->number ?? '')),
        ]);
        AuditLog::record(AuditAction::IntegrationGenerated, $case, $case->id, ['system' => 'justica']);

        return Pdf::loadView('relatorios.justica_pdf', [
            'case' => $case, 'process' => $process, 'summary' => $d['summary'], 'recipient' => $d['recipient'] ?? null,
            'author' => $request->user(), 'dispatch' => $dispatch,
            'types' => collect($case->violence_types)->map(fn ($v) => ViolenceType::from($v)->label())->implode(', '),
        ])->download('comunicacao-justica-'.$case->protocol.'.pdf');
    }
}
