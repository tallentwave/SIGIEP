<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\CaseStatus;
use App\Enums\Role;
use App\Enums\SessionStatus;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\Child;
use App\Models\ListeningSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EscutaTest extends TestCase
{
    use RefreshDatabase;

    private function user(Role $role): User
    {
        return User::factory()->role($role)->create();
    }

    private function makeCase(?User $creator = null, array $attrs = []): CaseRecord
    {
        $child = Child::create(['name' => 'Criança Teste', 'birth_date' => now()->subYears(8)]);

        return CaseRecord::create([
            'protocol' => CaseRecord::nextProtocol(), 'child_id' => $child->id,
            'condition' => 'vitima', 'violence_types' => ['sexual'], 'origin' => 'Conselho Tutelar',
            'received_at' => now(), 'status' => CaseStatus::Recebido,
            'initial_report' => 'RELATO-SIGILOSO-ABC', 'created_by' => $creator?->id, ...$attrs,
        ]);
    }

    private function casePayload(array $over = []): array
    {
        return [
            'child' => ['name' => 'Maria Teste', 'birth_date' => now()->subYears(9)->toDateString()],
            'guardians' => [['name' => 'Avó', 'relationship' => 'avó'], ['name' => '']],
            'case' => ['condition' => 'vitima', 'violence_types' => ['fisica'], 'origin' => 'Delegacia',
                'received_at' => now()->toDateString(), ...$over],
        ];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/casos')->assertRedirect('/login');
    }

    public function test_login_is_audited_and_inactive_user_is_blocked(): void
    {
        $u = User::factory()->create(['email' => 'a@b.c', 'password' => 'secret123']);
        $this->post('/login', ['email' => 'a@b.c', 'password' => 'secret123'])->assertRedirect('/');
        $this->assertDatabaseHas('audit_logs', ['user_id' => $u->id, 'action' => AuditAction::Login->value]);

        auth()->logout();
        $u->update(['active' => false]);
        $this->post('/login', ['email' => 'a@b.c', 'password' => 'secret123'])->assertSessionHasErrors('email');
    }

    public function test_store_case_generates_protocol_and_skips_blank_guardians(): void
    {
        $psi = $this->user(Role::Psicologo);
        $this->actingAs($psi)->post('/casos', $this->casePayload())->assertRedirect();

        $case = CaseRecord::firstOrFail();
        $this->assertSame('SIG-'.now()->year.'-00001', $case->protocol);
        $this->assertSame($psi->id, $case->responsible_id);
        $this->assertCount(1, $case->child->guardians);
    }

    public function test_protocol_sequence_increments(): void
    {
        $a = $this->makeCase();
        $b = $this->makeCase();
        $this->assertSame('SIG-'.now()->year.'-00001', $a->protocol);
        $this->assertSame('SIG-'.now()->year.'-00002', $b->protocol);
    }

    public function test_violence_type_is_required_and_future_dates_rejected(): void
    {
        $u = $this->user(Role::Recepcao);
        $this->actingAs($u)->post('/casos', $this->casePayload(['violence_types' => [], 'received_at' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors(['case.violence_types', 'case.received_at']);
    }

    public function test_user_without_access_cannot_view_case_and_denial_is_audited(): void
    {
        $case = $this->makeCase();
        $outsider = $this->user(Role::Psicologo);

        $this->actingAs($outsider)->get("/casos/{$case->id}")->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['user_id' => $outsider->id, 'action' => AuditAction::Denied->value]);
        $this->assertDatabaseMissing('audit_logs', ['user_id' => $outsider->id, 'action' => AuditAction::Viewed->value]);
    }

    public function test_listing_only_shows_accessible_cases(): void
    {
        $mine = $this->user(Role::Psicologo);
        $visible = $this->makeCase(null, ['responsible_id' => $mine->id]);
        $hidden = $this->makeCase();

        $this->actingAs($mine)->get('/casos')->assertSee($visible->protocol)->assertDontSee($hidden->protocol);
        $this->actingAs($this->user(Role::Coordenacao))->get('/casos')->assertSee($visible->protocol)->assertSee($hidden->protocol);
    }

    public function test_reception_sees_case_but_not_confidential_reports(): void
    {
        $rec = $this->user(Role::Recepcao);
        $case = $this->makeCase($rec);
        $psi = $this->user(Role::Psicologo);
        $session = $case->sessions()->create([
            'professional_id' => $psi->id, 'scheduled_at' => now()->addDay(), 'status' => SessionStatus::Agendada,
            'report' => 'RELATO-DA-ESCUTA-XYZ',
        ]);

        $this->actingAs($rec)->get("/casos/{$case->id}")->assertOk()->assertDontSee('RELATO-SIGILOSO-ABC');
        $this->actingAs($rec)->get("/escutas/{$session->id}/editar")->assertOk()->assertDontSee('RELATO-DA-ESCUTA-XYZ');
        $this->assertDatabaseMissing('audit_logs', ['user_id' => $rec->id, 'action' => AuditAction::ViewedReport->value]);
    }

    public function test_reception_cannot_overwrite_report(): void
    {
        $rec = $this->user(Role::Recepcao);
        $case = $this->makeCase($rec);
        $session = $case->sessions()->create([
            'professional_id' => $this->user(Role::Psicologo)->id, 'scheduled_at' => now()->addDay(),
            'status' => SessionStatus::Agendada, 'report' => 'ORIGINAL',
        ]);

        $this->actingAs($rec)->put("/escutas/{$session->id}", [
            'professional_id' => $session->professional_id, 'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i'),
            'duration_minutes' => 60, 'status' => 'agendada', 'report' => 'ADULTERADO',
        ])->assertRedirect();

        $this->assertSame('ORIGINAL', $session->fresh()->report);
    }

    public function test_technical_reading_report_is_audited_and_report_is_encrypted_at_rest(): void
    {
        $psi = $this->user(Role::Psicologo);
        $case = $this->makeCase($psi, ['responsible_id' => $psi->id]);
        $session = $case->sessions()->create([
            'professional_id' => $psi->id, 'scheduled_at' => now()->addDay(),
            'status' => SessionStatus::Agendada, 'report' => 'RELATO-DA-ESCUTA-XYZ',
        ]);

        $this->actingAs($psi)->get("/escutas/{$session->id}/editar")->assertSee('RELATO-DA-ESCUTA-XYZ');
        $this->assertDatabaseHas('audit_logs', ['user_id' => $psi->id, 'action' => AuditAction::ViewedReport->value]);

        $raw = DB::table('listening_sessions')->value('report');
        $this->assertStringNotContainsString('RELATO-DA-ESCUTA-XYZ', $raw);
        $this->assertStringNotContainsString('RELATO-SIGILOSO-ABC', DB::table('cases')->value('initial_report'));
    }

    public function test_scheduling_conflicts_by_professional_and_room_are_blocked(): void
    {
        $psi = $this->user(Role::Psicologo);
        $other = $this->user(Role::AssistenteSocial);
        $case = $this->makeCase($psi, ['responsible_id' => $psi->id]);
        $start = now()->addDays(2)->setTime(10, 0);

        $post = fn (User $prof, $when, $room) => $this->actingAs($psi)->post("/casos/{$case->id}/escutas", [
            'professional_id' => $prof->id, 'scheduled_at' => $when->format('Y-m-d H:i'),
            'duration_minutes' => 60, 'room' => $room,
        ]);

        $post($psi, $start, 'Sala 1')->assertSessionHasNoErrors();
        $this->assertSame(CaseStatus::EmEscuta, $case->fresh()->status);

        $post($psi, $start->copy()->addMinutes(30), 'Sala 2')->assertSessionHasErrors('scheduled_at'); // mesmo profissional
        $post($other, $start->copy()->addMinutes(30), 'Sala 1')->assertSessionHasErrors('scheduled_at'); // mesma sala
        $post($other, $start->copy()->addMinutes(60), 'Sala 1')->assertSessionHasNoErrors();           // adjacente: ok
        $this->assertSame(2, ListeningSession::count());
    }

    public function test_referral_flow_and_overdue(): void
    {
        $psi = $this->user(Role::Psicologo);
        $case = $this->makeCase($psi, ['responsible_id' => $psi->id]);

        $this->actingAs($psi)->post("/casos/{$case->id}/encaminhamentos", [
            'destination' => 'creas', 'sent_at' => now()->subDays(20)->toDateString(), 'due_at' => now()->subDays(5)->toDateString(),
        ])->assertSessionHasNoErrors();

        $ref = $case->referrals()->firstOrFail();
        $this->assertTrue($ref->isOverdue());
        $this->assertSame(CaseStatus::Encaminhado, $case->fresh()->status);

        $this->actingAs($psi)->patch("/encaminhamentos/{$ref->id}", ['status' => 'com_retorno'])->assertSessionHasNoErrors();
        $this->assertFalse($ref->fresh()->isOverdue());
        $this->assertNotNull($ref->fresh()->returned_at);
    }

    public function test_only_coordination_manages_team_and_audit_log(): void
    {
        $psi = $this->user(Role::Psicologo);
        $coord = $this->user(Role::Coordenacao);
        $case = $this->makeCase($psi, ['responsible_id' => $psi->id]);
        $newcomer = $this->user(Role::AssistenteSocial);

        $this->actingAs($psi)->put("/casos/{$case->id}/gestao", ['status' => 'encerrado'])->assertForbidden();
        $this->actingAs($psi)->get('/auditoria')->assertForbidden();

        $this->actingAs($coord)->put("/casos/{$case->id}/gestao", [
            'status' => 'encerrado', 'team' => [$newcomer->id], 'responsible_id' => $psi->id,
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($case->fresh()->closed_at);
        $this->actingAs($newcomer)->get("/casos/{$case->id}")->assertOk();
        $this->actingAs($coord)->get('/auditoria')->assertOk();
    }

    public function test_audit_changes_store_field_names_not_values_and_logs_are_immutable(): void
    {
        $psi = $this->user(Role::Psicologo);
        $case = $this->makeCase($psi, ['responsible_id' => $psi->id]);
        $this->actingAs($psi);
        $case->update(['initial_report' => 'NOVO-SEGREDO-123']);

        $log = AuditLog::where('action', AuditAction::Updated->value)->latest('id')->firstOrFail();
        $this->assertSame(['fields' => ['initial_report']], $log->changes);
        $this->assertStringNotContainsString('NOVO-SEGREDO-123', json_encode($log->toArray()));

        $this->expectException(\LogicException::class);
        $log->delete();
    }

    public function test_main_pages_render(): void
    {
        $coord = $this->user(Role::Coordenacao);
        $case = $this->makeCase($coord, ['urgent' => true]);
        $case->child->guardians()->create(['name' => 'Tia', 'is_suspected' => true]);
        $case->referrals()->create(['destination' => 'creas', 'status' => 'enviado', 'due_at' => now()->subDay()]);
        $this->actingAs($coord);

        foreach (['/', '/casos', '/casos/create', "/casos/{$case->id}", "/casos/{$case->id}/edit", '/auditoria'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/casos?q=Crian&status=recebido&urgent=1')->assertOk()->assertSee($case->protocol);
    }

    public function test_update_case_replaces_guardians(): void
    {
        $psi = $this->user(Role::Psicologo);
        $case = $this->makeCase($psi, ['responsible_id' => $psi->id]);
        $case->child->guardians()->create(['name' => 'Antigo']);

        $this->actingAs($psi)->put("/casos/{$case->id}", $this->casePayload(['origin' => 'Vara']))->assertRedirect();

        $this->assertSame(['Avó'], $case->child->guardians()->pluck('name')->all());
        $this->assertSame('Vara', $case->fresh()->origin);
    }
}
