<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAudit');

        $user = $request->user();
        $logs = AuditLog::with('user')
            // Coordenação só audita o próprio município; administrador vê tudo.
            ->when(! $user->isAdmin(), fn ($q) => $q->where('municipality_id', $user->municipality_id))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('case_id'), fn ($q) => $q->where('case_id', $request->integer('case_id')))
            ->latest('id')->paginate(50)->withQueryString();

        return view('auditoria.index', ['logs' => $logs, 'actions' => AuditAction::cases()]);
    }
}
