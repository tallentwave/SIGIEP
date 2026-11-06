<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\CaseStatus;
use App\Enums\Role;
use App\Enums\SessionStatus;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\ListeningSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function store(Request $request, CaseRecord $case)
    {
        $this->authorize('update', $case);

        $data = $request->validate([
            'professional_id' => ['required', $this->professionalRule($case)],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'between:15,240'],
            'room' => ['nullable', 'string', 'max:100'],
        ], [], ['professional_id' => 'profissional', 'scheduled_at' => 'data/hora']);

        $this->assertNoConflict($data, $case);

        $case->sessions()->create([...$data, 'status' => SessionStatus::Agendada]);
        if ($case->status === CaseStatus::Recebido) {
            $case->update(['status' => CaseStatus::EmEscuta]);
        }

        return back()->with('ok', 'Escuta agendada.');
    }

    public function edit(ListeningSession $session)
    {
        $this->authorize('update', $session->case);
        $canRead = auth()->user()->can('readReports', $session->case);

        if ($canRead) {
            AuditLog::record(AuditAction::ViewedReport, $session, $session->case_id);
        }

        return view('sessoes.edit', [
            'session' => $session->load('case.child', 'professional'),
            'canRead' => $canRead,
            'statuses' => SessionStatus::cases(),
            'professionals' => User::where('active', true)->where('municipality_id', $session->case->municipality_id)
                ->whereIn('role', [Role::Psicologo->value, Role::AssistenteSocial->value])->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, ListeningSession $session)
    {
        $this->authorize('update', $session->case);
        $canWrite = $request->user()->can('readReports', $session->case);

        $data = $request->validate([
            'professional_id' => ['required', $this->professionalRule($session->case)],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'between:15,240'],
            'room' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::enum(SessionStatus::class)],
            'accompanied_by' => ['nullable', 'string', 'max:255'],
            'report' => ['nullable', 'string', 'max:50000'],
            'technical_notes' => ['nullable', 'string', 'max:50000'],
        ]);

        // Recepção agenda, mas não lê nem escreve o conteúdo da escuta.
        if (! $canWrite) {
            unset($data['report'], $data['technical_notes'], $data['accompanied_by']);
        }

        if (SessionStatus::from($data['status']) === SessionStatus::Agendada) {
            $this->assertNoConflict($data, $session->case, $session);
        }

        if ($data['status'] === SessionStatus::Realizada->value && ! $session->performed_at) {
            $data['performed_at'] = now();
        }

        $session->update($data);

        return redirect()->route('casos.show', $session->case_id)->with('ok', 'Escuta atualizada.');
    }

    /** Mesmo profissional ou mesma sala não podem ter dois atendimentos sobrepostos. */
    private function professionalRule(CaseRecord $case)
    {
        return Rule::exists('users', 'id')->where('active', true)
            ->where('municipality_id', $case->municipality_id)
            ->whereIn('role', [Role::Psicologo->value, Role::AssistenteSocial->value]);
    }

    private function assertNoConflict(array $data, CaseRecord $case, ?ListeningSession $ignore = null): void
    {
        $start = Carbon::parse($data['scheduled_at']);
        $end = $start->copy()->addMinutes((int) $data['duration_minutes']);

        $busy = ListeningSession::where('status', SessionStatus::Agendada)
            ->whereHas('case', fn ($q) => $q->where('municipality_id', $case->municipality_id))
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->getKey()))
            ->where(fn ($q) => $q->where('professional_id', $data['professional_id'])
                ->when(filled($data['room'] ?? null), fn ($w) => $w->orWhere('room', $data['room'])))
            ->get()
            ->first(fn ($s) => $s->scheduled_at < $end
                && $s->scheduled_at->copy()->addMinutes($s->duration_minutes) > $start);

        if ($busy) {
            throw ValidationException::withMessages([
                'scheduled_at' => 'Conflito de agenda: profissional ou sala já ocupados em '
                    .$busy->scheduled_at->format('d/m/Y H:i').'.',
            ]);
        }
    }
}
