<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\Child;
use Illuminate\Http\Request;

/** Histórico por criança/adolescente, restrito aos casos que o usuário pode ver. */
class ChildController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', CaseRecord::class);

        $children = collect();
        if (strlen(trim((string) $request->query('q'))) >= 3) {
            $term = '%'.trim($request->query('q')).'%';
            $children = Child::whereIn('id', CaseRecord::visibleTo($request->user())->select('child_id'))
                ->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('social_name', 'like', $term)
                    ->orWhere('mother_name', 'like', $term)->orWhere('cpf', 'like', $term))
                ->orderBy('name')->limit(30)->get();
        }

        return view('atendidos.index', ['children' => $children]);
    }

    public function show(Request $request, Child $child)
    {
        $cases = CaseRecord::visibleTo($request->user())->where('child_id', $child->id)->latest('received_at')->get();
        abort_if($cases->isEmpty(), 403);
        AuditLog::record(AuditAction::Viewed, $child, null);

        return view('atendidos.show', ['child' => $child->load('guardians'), 'cases' => $cases]);
    }
}
