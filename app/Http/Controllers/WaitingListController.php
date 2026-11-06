<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\User;
use App\Notifications\CasosAtribuidos;
use App\Services\WaitingList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class WaitingListController extends Controller
{
    public function index(Request $request, WaitingList $list)
    {
        $this->authorize('viewAny', CaseRecord::class);
        $user = $request->user();
        $filter = array_key_exists($request->query('filtro', 'todos'), WaitingList::FILTERS) ? $request->query('filtro', 'todos') : 'todos';

        $cases = $list->cases($user, $filter);
        $counts = collect(array_keys(WaitingList::FILTERS))->mapWithKeys(fn ($f) => [$f => $list->cases($user, $f)->count()]);

        return view('fila.index', [
            'cases' => $cases, 'filter' => $filter, 'counts' => $counts, 'list' => $list,
            'canAssign' => $user->isCoordination(),
            'workload' => $user->isCoordination() ? $list->workload($user->municipality_id) : collect(),
        ]);
    }

    /** Coordenação atribui (ou reatribui) vários casos a um profissional de uma vez. */
    public function assign(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isCoordination(), 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
            'responsible_id' => ['required', Rule::exists('users', 'id')->where('active', true)
                ->where('municipality_id', $user->municipality_id)
                ->whereIn('role', [Role::Psicologo->value, Role::AssistenteSocial->value])],
        ], ['ids.required' => 'Selecione ao menos um caso.'], ['responsible_id' => 'profissional']);

        $assigned = [];
        foreach (CaseRecord::whereIn('id', $data['ids'])->get() as $case) {
            // A política garante município e que o caso não está anonimizado; ignora o resto em silêncio.
            if (! $user->can('manage', $case) || $case->responsible_id === (int) $data['responsible_id']) {
                continue;
            }
            $case->update(['responsible_id' => $data['responsible_id']]);
            AuditLog::record(AuditAction::Assigned, $case, $case->id);
            $assigned[] = $case->protocol;
        }

        if ($assigned) {
            try {
                Notification::send(User::find($data['responsible_id']), new CasosAtribuidos($assigned));
            } catch (\Throwable $e) {
                report($e); // o aviso não pode desfazer a atribuição
            }
        }

        return back()->with('ok', count($assigned) ? count($assigned).' caso(s) atribuído(s).' : 'Nenhum caso foi alterado (já estavam com esse profissional ou não podem ser reatribuídos).');
    }
}
