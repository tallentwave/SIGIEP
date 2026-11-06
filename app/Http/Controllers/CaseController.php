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
use App\Support\Ficha;
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
        $v = $request->validate([
            'child.name' => ['required', 'string', 'max:255'],
            'child.social_name' => ['nullable', 'string', 'max:255'],
            'child.birth_date' => ['nullable', 'date', 'before_or_equal:today', 'after:'.now()->subYears(19)->toDateString()],
            'child.birth_date_estimated' => ['boolean'],
            'child.sex' => ['nullable', 'string', 'max:20'],
            'child.cpf' => ['nullable', 'string', 'max:14'],
            'child.mother_name' => ['nullable', 'string', 'max:255'],
            'child.school' => ['nullable', 'string', 'max:255'],
            'child.address' => ['nullable', 'string', 'max:255'],
            'child.neighborhood' => ['nullable', 'string', 'max:255'],
            'child.city' => ['nullable', 'string', 'max:255'],
            'child.has_disability' => ['boolean'],
            'child.disability_notes' => ['nullable', 'string', 'max:255'],
            'guardians' => ['array', 'max:6'],
            'guardians.*.name' => ['nullable', 'string', 'max:255'],
            'guardians.*.relationship' => ['nullable', 'string', 'max:100'],
            'guardians.*.phone' => ['nullable', 'string', 'max:30'],
            'guardians.*.cpf' => ['nullable', 'string', 'max:14'],
            'guardians.*.is_suspected' => ['boolean'],
            'guardians.*.can_be_contacted' => ['boolean'],
            'case.condition' => ['required', Rule::enum(Condition::class)],
            'case.violence_types' => ['required', 'array', 'min:1'],
            'case.violence_types.*' => [Rule::enum(ViolenceType::class)],
            'case.origin' => ['required', 'string', 'max:255'],
            'case.origin_document' => ['nullable', 'string', 'max:255'],
            'case.received_at' => ['required', 'date', 'before_or_equal:today'],
            'case.violence_date' => ['nullable', 'date', 'before_or_equal:today'],
            'case.alleged_author' => ['nullable', 'string', 'max:255'],
            'case.urgent' => ['boolean'],
            'case.initial_report' => ['nullable', 'string', 'max:20000'],
            'child_id' => ['nullable', 'integer'],
            ...Ficha::rules('ficha'),
        ], [], [
            'child.name' => 'nome da criança/adolescente',
            'child.birth_date' => 'data de nascimento',
            'case.violence_types' => 'tipo de violência',
            'case.origin' => 'órgão demandante',
            'case.received_at' => 'data de recebimento',
        ]);

        // Descarta linhas de responsável em branco e normaliza checkboxes.
        $v['guardians'] = collect($v['guardians'] ?? [])
            ->filter(fn ($g) => filled($g['name'] ?? null))
            ->map(fn ($g) => [...$g,
                'is_suspected' => (bool) ($g['is_suspected'] ?? false),
                'can_be_contacted' => (bool) ($g['can_be_contacted'] ?? false),
            ])->values()->all();

        $v['child']['birth_date_estimated'] = (bool) ($v['child']['birth_date_estimated'] ?? false);
        $v['child']['has_disability'] = (bool) ($v['child']['has_disability'] ?? false);
        $v['case']['urgent'] = (bool) ($v['case']['urgent'] ?? false);
        $v['ficha'] = Ficha::clean($v['ficha'] ?? []);

        return $v;
    }
}
