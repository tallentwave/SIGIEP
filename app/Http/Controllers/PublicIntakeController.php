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
use App\Models\Guardian;
use App\Models\Municipality;
use App\Models\User;
use App\Notifications\NovoCasoPublico;
use App\Services\FlowService;
use App\Support\CaseInput;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

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
            'case' => new CaseRecord,
            'child' => new Child,
            'guardians' => collect([new Guardian]),
            'violenceTypes' => ViolenceType::cases(),
            'conditions' => Condition::cases(),
            'canReadSensitive' => false,
        ]);
    }

    public function store(Request $request, string $slug)
    {
        $municipality = $this->municipality($slug);

        // Honeypot: robôs preenchem; pessoas não veem o campo. Responde como sucesso para não orientar o robô.
        if ($request->filled('website')) {
            return redirect()->route('denuncia.index');
        }

        $v = CaseInput::normalize($request->validate(CaseInput::rules(public: true), [], CaseInput::attributes()));

        $ficha = $v['ficha'];
        if (! empty($ficha['notificante_anonimo'])) {
            unset($ficha['notificante_nome'], $ficha['notificante_telefone'], $ficha['notificante_email'], $ficha['vinculo_notificante']);
        }

        $case = DB::transaction(function () use ($v, $municipality, $ficha) {
            $child = Child::create([
                ...$v['child'],
                'name' => $v['child']['name'] ?? null ?: 'Não identificado(a)',
                'city' => $v['child']['city'] ?? null ?: $municipality->name,
            ]);
            foreach ($v['guardians'] as $g) {
                $child->guardians()->create($g);
            }

            $case = CaseRecord::createWithProtocol([
                ...$v['case'],
                'municipality_id' => $municipality->id,
                'source' => 'publica',
                'child_id' => $child->id,
                'status' => CaseStatus::Recebido,
                'origin' => 'Denúncia pública',
                'received_at' => now()->toDateString(),
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
