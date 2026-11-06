<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\CaseStatus;
use App\Enums\Condition;
use App\Enums\ReferralDestination;
use App\Enums\ReferralStatus;
use App\Enums\Role;
use App\Enums\SessionStatus;
use App\Enums\ViolenceType;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\User;
use App\Services\FlowService;
use App\Support\CaseInput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CaseController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', CaseRecord::class);

        $cases = CaseRecord::with('child', 'responsible')
            ->visibleTo($request->user())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->boolean('urgent'), fn ($q) => $q->where('urgent', true))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('protocol', 'like', $term)
                    ->orWhereHas('child', fn ($c) => $c->where('name', 'like', $term)->orWhere('social_name', 'like', $term)));
            })
            ->orderByDesc('urgent')->latest('received_at')->latest('id')
            ->paginate(20)->withQueryString();

        return view('casos.index', ['cases' => $cases, 'statuses' => CaseStatus::cases()]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', CaseRecord::class);

        $child = new Child;
        if ($request->filled('child')) {
            $child = $this->accessibleChild($request->user(), $request->integer('child'));
        }

        return view('casos.form', $this->formData(new CaseRecord, $child) + ['canReadSensitive' => true]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', CaseRecord::class);
        $data = $this->validated($request);

        $user = $request->user();
        $case = DB::transaction(function () use ($data, $request, $user) {
            if ($request->filled('child_id')) {
                // Novo caso para criança já atendida: atualiza o cadastro existente.
                $child = $this->accessibleChild($user, $request->integer('child_id'));
                $child->update($data['child']);
            } else {
                $child = Child::create($data['child']);
            }
            $child->guardians()->delete();
            foreach ($data['guardians'] as $g) {
                $child->guardians()->create($g);
            }

            $case = CaseRecord::create([
                ...$data['case'],
                'ficha' => $data['ficha'],
                'municipality_id' => $user->municipality_id,
                'source' => 'interna',
                'protocol' => CaseRecord::nextProtocol(),
                'child_id' => $child->id,
                'status' => CaseStatus::Recebido,
                'created_by' => $request->user()->id,
                // Só coordenação define responsável; técnico que cadastra assume o caso.
                'responsible_id' => $request->user()->isTechnical() ? $request->user()->id : null,
            ]);

            app(FlowService::class)->apply($case->load('municipality'));

            return $case;
        });

        return redirect()->route('casos.show', $case)->with('ok', "Caso {$case->protocol} cadastrado. Fluxo municipal aplicado.");
    }

    public function show(CaseRecord $case)
    {
        $this->authorize('view', $case);
        AuditLog::record(AuditAction::Viewed, $case, $case->id);

        $canReadReports = auth()->user()->can('readReports', $case);
        $case->load('child.guardians', 'responsible', 'team', 'referrals',
            'sessions.professional');

        return view('casos.show', [
            'case' => $case,
            'canReadReports' => $canReadReports,
            'canManage' => auth()->user()->can('manage', $case),
            'canClaim' => auth()->user()->can('claim', $case),
            'professionals' => User::where('active', true)->where('municipality_id', $case->municipality_id)
                ->whereIn('role', [Role::Psicologo->value, Role::AssistenteSocial->value])->orderBy('name')->get(),
            'allUsers' => User::where('active', true)->where('municipality_id', $case->municipality_id)
                ->where('role', '!=', Role::Administrador->value)->orderBy('name')->get(),
            'sessionStatuses' => SessionStatus::cases(),
            'destinations' => ReferralDestination::cases(),
            'referralStatuses' => ReferralStatus::cases(),
            'statuses' => CaseStatus::cases(),
        ]);
    }

    public function edit(Request $request, CaseRecord $case)
    {
        $this->authorize('update', $case);
        $case->load('child.guardians');

        return view('casos.form', $this->formData($case, $case->child)
            + ['canReadSensitive' => $request->user()->can('readReports', $case)]);
    }

    public function update(Request $request, CaseRecord $case)
    {
        $this->authorize('update', $case);
        $data = $this->validated($request);

        // Quem não lê o conteúdo sigiloso também não o sobrescreve (campos nem chegam no formulário).
        $canRead = $request->user()->can('readReports', $case);
        if (! $canRead) {
            unset($data['case']['initial_report']);
        }

        DB::transaction(function () use ($data, $case, $canRead) {
            $case->child->update($data['child']);
            $case->child->guardians()->delete();
            foreach ($data['guardians'] as $g) {
                $case->child->guardians()->create($g);
            }
            $case->update($canRead ? [...$data['case'], 'ficha' => $data['ficha']] : $data['case']);
        });

        return redirect()->route('casos.show', $case)->with('ok', 'Caso atualizado.');
    }

    /** Coordenação: responsável, equipe e status do caso. */
    public function manage(Request $request, CaseRecord $case)
    {
        $this->authorize('manage', $case);

        $data = $request->validate([
            'responsible_id' => ['nullable', Rule::exists('users', 'id')->where('active', true)
                ->where('municipality_id', $case->municipality_id)],
            'team' => ['array'],
            'team.*' => [Rule::exists('users', 'id')->where('active', true)->where('municipality_id', $case->municipality_id)],
            'status' => ['required', Rule::enum(CaseStatus::class)],
        ]);

        DB::transaction(function () use ($case, $data) {
            $status = CaseStatus::from($data['status']);
            $case->update([
                'responsible_id' => $data['responsible_id'] ?? null,
                'status' => $status,
                'closed_at' => $status === CaseStatus::Encerrado ? ($case->closed_at ?? now()) : null,
            ]);
            $case->team()->sync($data['team'] ?? []);
        });

        return back()->with('ok', 'Responsável, equipe e situação atualizados.');
    }

    /** Profissional técnico assume um caso da fila do município. */
    public function claim(Request $request, CaseRecord $case)
    {
        $this->authorize('claim', $case);

        // Atualização condicional evita que dois profissionais assumam o mesmo caso.
        $won = CaseRecord::whereKey($case->id)->whereNull('responsible_id')
            ->update(['responsible_id' => $request->user()->id]);

        if (! $won) {
            return back()->withErrors(['claim' => 'Outro profissional já assumiu este caso.']);
        }
        AuditLog::record(AuditAction::Claimed, $case, $case->id);

        return back()->with('ok', 'Você assumiu o caso.');
    }

    /** Coordenação: (re)aplica o fluxo municipal sem duplicar etapas. */
    public function applyFlow(CaseRecord $case)
    {
        $this->authorize('manage', $case);
        $n = app(FlowService::class)->apply($case);

        return back()->with('ok', $n ? "{$n} encaminhamento(s) gerado(s) pelo fluxo." : 'Fluxo já aplicado: nenhuma etapa nova.');
    }

    private function accessibleChild(User $user, int $id): Child
    {
        abort_unless(CaseRecord::visibleTo($user)->where('child_id', $id)->exists(), 403);

        return Child::findOrFail($id);
    }

    private function formData(CaseRecord $case, Child $child): array
    {
        return [
            'case' => $case,
            'child' => $child,
            'guardians' => $child->exists ? $child->guardians : collect([new Guardian]),
            'conditions' => Condition::cases(),
            'violenceTypes' => ViolenceType::cases(),
            'ficha' => $case->ficha ?? [],
        ];
    }

    private function validated(Request $request): array
    {
        $v = $request->validate(
            [...CaseInput::rules(), 'child_id' => ['nullable', 'integer']],
            [],
            CaseInput::attributes(),
        );

        return CaseInput::normalize($v);
    }
}
