<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    /** Relatório individual do caso. Contém conteúdo sigiloso: exige permissão de leitura e é auditado. */
    public function case(CaseRecord $case)
    {
        $this->authorize('readReports', $case);
        AuditLog::record(AuditAction::Exported, $case, $case->id, ['report' => 'caso']);

        $case->load('child.guardians', 'municipality', 'responsible', 'referrals', 'sessions.professional');

        return Pdf::loadView('relatorios.caso_pdf', ['case' => $case, 'generatedBy' => auth()->user()])
            ->download($case->protocol.'.pdf');
    }
}
