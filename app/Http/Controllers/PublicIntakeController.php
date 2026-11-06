<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\CaseStatus;
use App\Enums\Condition;
use App\Enums\Role;
use App\Enums\ViolenceType;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\Child;
use App\Models\Municipality;
use App\Models\User;
use App\Notifications\NovoCasoPublico;
use App\Services\FlowService;
use App\Support\Ficha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/** Recebimento de denúncias pela população (sem login), por município. */
class PublicIntakeController extends Controller
{
    public function index()
    {
        return view('publico.municipios', [
            'municipalities' => Municipality::where('active', true)->where('accepts_public_reports', true)->orderBy('name')->get(),
        ]);
    }

    public function create(string $slug)
    {
        return view('publico.form', [
            'municipality' => $this->municipality($slug),
            'violenceTypes' => ViolenceType::cases(),
            'conditions' => Condition::cases(),
        ]);
    }

    public function store(Request $request, string $slug)
    {
        $municipality = $this->municipality($slug);

        // Honeypot: robôs preenchem; pessoas não veem o campo. Responde como sucesso para não orientar o robô.
        if ($request->filled('website')) {
            return redirect()->route('denuncia.index');
        }

        $v = $request->validate([
            'condition' => ['required', Rule::enum(Condition::class)],
            'violence_types' => ['required', 'array', 'min:1'],
            'violence_types.*' => [Rule::enum(ViolenceType::class)],
            'description' => ['required', 'string', 'min:10', 'max:10000'],
            'urgent' => ['boolean'],
            'violence_date' => ['nullable', 'date', 'before_or_equal:today'],
            'child.name' => ['nullable', 'string', 'max:255'],
            'child.age' => ['nullable', 'integer', 'between:0,18'],
            'child.sex' => ['nullable', 'string', 'max:20'],
            'child.school' => ['nullable', 'string', 'max:255'],
            'child.address' => ['nullable', 'string', 'max:255'],
            'child.neighborhood' => ['nullable', 'string', 'max:255'],
            'guardian.name' => ['nullable', 'string', 'max:255'],
            'guardian.phone' => ['nullable', 'string', 'max:30'],
            ...Ficha::rules('ficha'),
        ], [], [
            'condition' => 'condição', 'violence_types' => 'tipo de violência', 'description' => 'descrição dos fatos',
            'child.age' => 'idade',
        ]);

        $ficha = Ficha::clean($v['ficha'] ?? []);
        if (! empty($ficha['notificante_anonimo'])) {
            unset($ficha['notificante_nome'], $ficha['notificante_telefone'], $ficha['notificante_email'], $ficha['vinculo_notificante']);
        }

        $case = DB::transaction(function () use ($v, $municipality, $ficha) {
            $age = $v['child']['age'] ?? null;
            $child = Child::create([
                'name' => $v['child']['name'] ?? null ?: 'Não identificado(a)',
                'birth_date' => $age !== null ? now()->subYears((int) $age)->toDateString() : null,
                'birth_date_estimated' => $age !== null,
                'sex' => $v['child']['sex'] ?? null,
                'school' => $v['child']['school'] ?? null,
                'address' => $v['child']['address'] ?? null,
                'neighborhood' => $v['child']['neighborhood'] ?? null,
                'city' => $municipality->name,
            ]);
            if (filled($v['guardian']['name'] ?? null)) {
                $child->guardians()->create($v['guardian']);
            }

            $case = CaseRecord::create([
                'protocol' => CaseRecord::nextProtocol(),
                'municipality_id' => $municipality->id,
                'source' => 'publica',
                'child_id' => $child->id,
                'condition' => $v['condition'],
                'violence_types' => $v['violence_types'],
                'status' => CaseStatus::Recebido,
                'origin' => 'Denúncia pública',
                'received_at' => now()->toDateString(),
                'violence_date' => $v['violence_date'] ?? null,
                'urgent' => (bool) ($v['urgent'] ?? false),
                'initial_report' => $v['description'],
                'ficha' => $ficha,
            ]);

            app(FlowService::class)->apply($case->load('municipality'));

            return $case;
        });

        AuditLog::create(['action' => AuditAction::PublicReport, 'subject_type' => $case->getMorphClass(), 'subject_id' => $case->id,
            'case_id' => $case->id, 'municipality_id' => $municipality->id, 'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255)]);

        // O aviso não pode derrubar o recebimento da denúncia.
        try {
            $coord = User::where('municipality_id', $municipality->id)->where('active', true)
                ->where('role', Role::Coordenacao->value)->get();
            Notification::send($coord, new NovoCasoPublico($case));
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('denuncia.recebida', $municipality->slug)
            ->with('protocol', $case->protocol)->with('urgent', $case->urgent);
    }

    public function received(string $slug)
    {
        abort_unless(session()->has('protocol'), 404);

        return view('publico.recebida', ['municipality' => $this->municipality($slug)]);
    }

    private function municipality(string $slug): Municipality
    {
        return Municipality::where('slug', $slug)->where('active', true)->where('accepts_public_reports', true)->firstOrFail();
    }
}
