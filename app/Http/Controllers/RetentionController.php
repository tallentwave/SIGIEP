<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\CaseStatus;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\Municipality;
use App\Services\Anonymizer;
use Illuminate\Http\Request;

class RetentionController extends Controller
{
    public function index(Request $request, Anonymizer $anonymizer)
    {
        $this->authorize('viewRetention');
        $user = $request->user();
        $municipality = $user->isAdmin()
            ? Municipality::findOrFail($request->integer('municipio') ?: Municipality::orderBy('name')->value('id'))
            : $user->municipality;

        return view('retencao.index', [
            'municipality' => $municipality,
            'municipalities' => $user->isAdmin() ? Municipality::orderBy('name')->get() : collect(),
            'eligible' => $anonymizer->eligible($municipality->id)->count(),
            'dueSoon' => $anonymizer->dueSoon($municipality->id)->orderBy('closed_at')->limit(100)->get(),
            'held' => CaseRecord::where('municipality_id', $municipality->id)->where('legal_hold', true)->orderBy('protocol')->get(),
            'anonymized' => CaseRecord::withTrashed()->where('municipality_id', $municipality->id)->whereNotNull('anonymized_at')->count(),
            'anonymizer' => $anonymizer,
            'auditYears' => config('sigiep.audit_retention_years'),
            'auto' => config('sigiep.retention.auto'),
        ]);
    }

    /** Aplica ou retira a guarda legal (ex.: processo judicial em curso, requisição do MP). */
    public function hold(Request $request, CaseRecord $case)
    {
        $this->authorize('retain', $case);

        if ($case->legal_hold) {
            $case->forceFill(['legal_hold' => false, 'legal_hold_reason' => null, 'legal_hold_at' => null, 'legal_hold_by' => null])->save();
            AuditLog::record(AuditAction::LegalHoldLifted, $case, $case->id);

            return back()->with('ok', 'Guarda legal retirada: o caso volta a seguir o prazo de retenção.');
        }

        $d = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:255']], [], ['reason' => 'motivo da guarda legal']);
        $case->forceFill(['legal_hold' => true, 'legal_hold_reason' => $d['reason'], 'legal_hold_at' => now(), 'legal_hold_by' => $request->user()->id])->save();
        AuditLog::record(AuditAction::LegalHoldSet, $case, $case->id);

        return back()->with('ok', 'Guarda legal aplicada: o caso não será anonimizado enquanto durar.');
    }

    /** Anonimização antecipada, manual e irreversível. Exige caso encerrado, sem guarda legal e confirmação. */
    public function anonymize(Request $request, CaseRecord $case, Anonymizer $anonymizer)
    {
        $this->authorize('retain', $case);
        $d = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:255'],
            'confirm' => ['required', 'in:'.$case->protocol],
        ], ['confirm.in' => 'Digite exatamente o protocolo do caso para confirmar.'], ['reason' => 'justificativa', 'confirm' => 'confirmação']);

        if ($case->status !== CaseStatus::Encerrado) {
            return back()->withErrors(['reason' => 'Só casos encerrados podem ser anonimizados.']);
        }
        if ($case->legal_hold) {
            return back()->withErrors(['reason' => 'O caso está sob guarda legal. Retire-a antes.']);
        }

        $anonymizer->anonymize($case->load('municipality'), 'manual', $request->user()->id);
        AuditLog::record(AuditAction::Updated, $case, $case->id, ['justificativa_anonimizacao' => true]);

        return redirect()->route('casos.show', $case)->with('ok', 'Caso anonimizado de forma irreversível.');
    }
}
