<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\CaseStatus;
use App\Enums\ReferralDestination;
use App\Enums\ReferralStatus;
use App\Enums\Role;
use App\Enums\SessionStatus;
use App\Models\CaseRecord;
use App\Models\Child;
use App\Models\Municipality;
use App\Models\User;
use App\Notifications\AlertasDiarios;
use App\Notifications\NovoCasoPublico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MunicipiosFluxoTest extends TestCase
{
    use RefreshDatabase;

    private function muni(string $name = 'Alfa'): Municipality
    {
        return Municipality::factory()->create(['name' => $name, 'slug' => strtolower($name)]);
    }

    private function user(Role $role, ?Municipality $m = null): User
    {
        $u = User::factory()->role($role);

        return $m ? $u->inMunicipality($m)->create() : $u->create();
    }

    private function makeCase(Municipality $m, array $attrs = []): CaseRecord
    {
        $child = Child::create(['name' => 'Criança '.uniqid(), 'birth_date' => now()->subYears(8)]);

        return CaseRecord::create([
            'protocol' => CaseRecord::nextProtocol(), 'municipality_id' => $m->id, 'child_id' => $child->id,
            'condition' => 'vitima', 'violence_types' => ['fisica'], 'origin' => 'CT',
            'received_at' => now(), 'status' => CaseStatus::Recebido, ...$attrs,
        ]);
    }

    private function publicPayload(array $over = []): array
    {
        return [
            'child' => ['name' => 'Joana', 'birth_date' => now()->subYears(7)->toDateString(), 'birth_date_estimated' => 1],
            'guardians' => [['name' => 'Avó', 'phone' => '1199999']],
            'case' => ['condition' => 'vitima', 'violence_types' => ['sexual'], 'initial_report' => 'Descrição suficiente dos fatos.'],
            ...$over,
        ];
    }

    // ---------- isolamento por município ----------

    public function test_users_never_see_other_municipalities_cases(): void
    {
        $a = $this->muni('Alfa');
        $b = $this->muni('Beta');
        $caseB = $this->makeCase($b);
        $coordA = $this->user(Role::Coordenacao, $a);
        $psiA = $this->user(Role::Psicologo, $a);

        foreach ([$coordA, $psiA] as $u) {
            $this->actingAs($u)->get("/casos/{$caseB->id}")->assertForbidden();
            $this->actingAs($u)->get('/casos')->assertDontSee($caseB->protocol);
        }
        $this->actingAs($coordA)->put("/casos/{$caseB->id}/gestao", ['status' => 'encerrado'])->assertForbidden();
        $this->actingAs($coordA)->get("/atendidos/{$caseB->child_id}")->assertForbidden();
    }

    public function test_cannot_assign_professional_from_another_municipality(): void
    {
        $a = $this->muni('Alfa');
        $b = $this->muni('Beta');
        $case = $this->makeCase($a);
        $coord = $this->user(Role::Coordenacao, $a);
        $foreign = $this->user(Role::Psicologo, $b);

        $this->actingAs($coord)->put("/casos/{$case->id}/gestao", ['status' => 'em_escuta', 'responsible_id' => $foreign->id])
            ->assertSessionHasErrors('responsible_id');
        $this->actingAs($coord)->post("/casos/{$case->id}/escutas", [
            'professional_id' => $foreign->id, 'scheduled_at' => now()->addDay()->format('Y-m-d H:i'), 'duration_minutes' => 60,
        ])->assertSessionHasErrors('professional_id');
    }

    public function test_administrator_manages_system_but_cannot_read_cases(): void
    {
        $m = $this->muni();
        $case = $this->makeCase($m);
        $admin = $this->user(Role::Administrador);
        $admin->update(['municipality_id' => null]);

        $this->actingAs($admin)->get("/casos/{$case->id}")->assertForbidden();
        $this->actingAs($admin)->get('/casos')->assertForbidden();
        $this->actingAs($admin)->get('/')->assertRedirect(route('admin.municipios.index'));
        $this->actingAs($admin)->get('/admin/usuarios')->assertOk();
        $this->actingAs($admin)->get('/estatisticas')->assertOk();
        $this->actingAs($this->user(Role::Coordenacao, $m))->get('/admin/usuarios')->assertForbidden();
    }

    // ---------- fila e assumir caso ----------

    public function test_queue_is_visible_to_municipal_staff_and_claim_is_exclusive(): void
    {
        $m = $this->muni();
        $case = $this->makeCase($m); // sem responsável = fila
        $p1 = $this->user(Role::Psicologo, $m);
        $p2 = $this->user(Role::AssistenteSocial, $m);

        $this->actingAs($p1)->get("/casos/{$case->id}")->assertOk()->assertSee('Assumir este caso');
        $this->actingAs($p1)->post("/casos/{$case->id}/assumir")->assertSessionHasNoErrors();
        $this->assertSame($p1->id, $case->fresh()->responsible_id);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $p1->id, 'action' => AuditAction::Claimed->value]);

        // Depois de assumido, p2 perde o acesso e não consegue assumir.
        $this->actingAs($p2)->get("/casos/{$case->id}")->assertForbidden();
        $this->actingAs($p2)->post("/casos/{$case->id}/assumir")->assertForbidden();
    }

    public function test_reception_cannot_claim(): void
    {
        $m = $this->muni();
        $case = $this->makeCase($m);
        $this->actingAs($this->user(Role::Recepcao, $m))->post("/casos/{$case->id}/assumir")->assertForbidden();
    }

    // ---------- fluxo municipal ----------

    public function test_flow_generates_referrals_respecting_type_and_urgency_filters(): void
    {
        $m = $this->muni();
        $m->flowSteps()->createMany([
            ['position' => 1, 'name' => 'CT', 'destination' => ReferralDestination::ConselhoTutelar, 'due_days' => 1],
            ['position' => 2, 'name' => 'Saúde sexual', 'destination' => ReferralDestination::Saude, 'due_days' => 2, 'violence_types' => ['sexual']],
            ['position' => 3, 'name' => 'Polícia urgente', 'destination' => ReferralDestination::Policia, 'due_days' => 1, 'only_urgent' => true],
            ['position' => 4, 'name' => 'Inativa', 'destination' => ReferralDestination::Cras, 'due_days' => 1, 'active' => false],
        ]);
        $psi = $this->user(Role::Psicologo, $m);

        $payload = fn (array $case) => [
            'child' => ['name' => 'X'], 'guardians' => [],
            'case' => ['condition' => 'vitima', 'origin' => 'CT', 'received_at' => now()->toDateString(), ...$case],
        ];

        $this->actingAs($psi)->post('/casos', $payload(['violence_types' => ['fisica']]))->assertSessionHasNoErrors();
        $this->assertSame(['CT'], CaseRecord::first()->referrals()->pluck('destination_detail')->all());

        $this->actingAs($psi)->post('/casos', $payload(['violence_types' => ['sexual'], 'urgent' => 1]))->assertSessionHasNoErrors();
        $names = CaseRecord::latest('id')->first()->referrals()->orderBy('id')->pluck('destination_detail')->all();
        $this->assertSame(['CT', 'Saúde sexual', 'Polícia urgente'], $names);
        $this->assertSame(now()->addDay()->toDateString(), CaseRecord::latest('id')->first()->referrals()->first()->due_at->toDateString());
    }

    public function test_applying_flow_twice_does_not_duplicate(): void
    {
        $m = $this->muni();
        $m->flowSteps()->create(['name' => 'CT', 'destination' => ReferralDestination::ConselhoTutelar, 'due_days' => 1]);
        $case = $this->makeCase($m);
        $coord = $this->user(Role::Coordenacao, $m);

        $this->actingAs($coord)->post("/casos/{$case->id}/fluxo");
        $this->actingAs($coord)->post("/casos/{$case->id}/fluxo");
        $this->assertSame(1, $case->referrals()->count());
    }

    public function test_coordination_edits_own_municipality_flow_only(): void
    {
        $a = $this->muni('Alfa');
        $b = $this->muni('Beta');
        $coord = $this->user(Role::Coordenacao, $a);

        $this->actingAs($coord)->get("/admin/municipios/{$a->id}/fluxo")->assertOk();
        $this->actingAs($coord)->get("/admin/municipios/{$b->id}/fluxo")->assertForbidden();
        $this->actingAs($coord)->post("/admin/municipios/{$a->id}/etapas", [
            'name' => 'Nova', 'destination' => 'creas', 'due_days' => 7, 'violence_types' => ['sexual'],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['sexual'], $a->flowSteps()->first()->violence_types);
        $this->actingAs($coord)->post("/admin/municipios/{$b->id}/etapas", ['name' => 'x', 'destination' => 'creas', 'due_days' => 1])->assertForbidden();

        // Etapa de outro município não pode ser editada pela rota do meu.
        $stepB = $b->flowSteps()->create(['name' => 'B', 'destination' => ReferralDestination::Creas, 'due_days' => 1]);
        $this->actingAs($coord)->put("/admin/municipios/{$a->id}/etapas/{$stepB->id}", ['name' => 'hack', 'destination' => 'creas', 'due_days' => 1])->assertNotFound();
    }

    // ---------- denúncia pública ----------

    public function test_public_report_creates_queued_case_applies_flow_and_notifies_coordination(): void
    {
        Notification::fake();
        $m = $this->muni();
        $m->flowSteps()->create(['name' => 'CT', 'destination' => ReferralDestination::ConselhoTutelar, 'due_days' => 1]);
        $coord = $this->user(Role::Coordenacao, $m);

        $this->get("/denuncia/{$m->slug}")->assertOk()->assertSee('190');
        $this->post("/denuncia/{$m->slug}", $this->publicPayload(['case' => ['condition' => 'vitima', 'violence_types' => ['sexual'], 'initial_report' => 'Descrição suficiente.', 'urgent' => 1]]))->assertRedirect("/denuncia/{$m->slug}/recebida");

        $case = CaseRecord::firstOrFail();
        $this->assertSame('publica', $case->source);
        $this->assertNull($case->responsible_id);
        $this->assertTrue($case->urgent);
        $this->assertSame($m->id, $case->municipality_id);
        $this->assertSame(1, $case->referrals()->count());
        $this->assertSame('Avó', $case->child->guardians()->first()->name);
        $this->assertTrue($case->child->birth_date_estimated);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::PublicReport->value, 'case_id' => $case->id]);
        Notification::assertSentTo($coord, NovoCasoPublico::class);

        $this->get("/denuncia/{$m->slug}/recebida")->assertOk();
        $this->get("/denuncia/{$m->slug}/recebida")->assertNotFound(); // flash consumido: protocolo não reaparece
    }

    public function test_public_report_success_page_shows_protocol(): void
    {
        Notification::fake();
        $m = $this->muni();
        $this->followingRedirects()->post("/denuncia/{$m->slug}", $this->publicPayload())
            ->assertSee('SIG-'.now()->year.'-00001');
    }

    public function test_anonymous_report_discards_contact_data_and_report_is_encrypted(): void
    {
        Notification::fake();
        $m = $this->muni();
        $this->post("/denuncia/{$m->slug}", $this->publicPayload([
            'case' => ['condition' => 'vitima', 'violence_types' => ['sexual'], 'initial_report' => 'SEGREDO-DA-DENUNCIA-777'],
            'ficha' => ['notificante_anonimo' => 1, 'notificante_nome' => 'Fulano', 'notificante_telefone' => '9999', 'local_ocorrencia' => 'residencia'],
        ]))->assertSessionHasNoErrors();

        $case = CaseRecord::firstOrFail();
        $this->assertArrayNotHasKey('notificante_nome', $case->ficha);
        $this->assertArrayNotHasKey('notificante_telefone', $case->ficha);
        $this->assertSame('residencia', $case->ficha['local_ocorrencia']);
        $this->assertStringNotContainsString('SEGREDO', DB::table('cases')->value('initial_report'));
        $this->assertStringNotContainsString('residencia', DB::table('cases')->value('ficha'));
    }

    public function test_public_report_validation_honeypot_and_closed_municipality(): void
    {
        Notification::fake();
        $m = $this->muni();

        $this->post("/denuncia/{$m->slug}", $this->publicPayload(['case' => ['condition' => 'vitima', 'violence_types' => [], 'initial_report' => 'curta']]))
            ->assertSessionHasErrors(['case.violence_types', 'case.initial_report']);
        $this->post("/denuncia/{$m->slug}", $this->publicPayload(['website' => 'http://spam']))->assertRedirect('/denuncia');
        $this->post("/denuncia/{$m->slug}", $this->publicPayload(['ficha' => ['zona' => 'marte']]))->assertSessionHasErrors('ficha.zona');
        $this->assertSame(0, CaseRecord::count());

        $m->update(['accepts_public_reports' => false]);
        $this->get("/denuncia/{$m->slug}")->assertNotFound();
        $this->post("/denuncia/{$m->slug}", $this->publicPayload())->assertNotFound();
    }

    public function test_public_report_is_rate_limited(): void
    {
        Notification::fake();
        $m = $this->muni();
        for ($i = 0; $i < 5; $i++) {
            $this->post("/denuncia/{$m->slug}", $this->publicPayload())->assertRedirect();
        }
        $this->post("/denuncia/{$m->slug}", $this->publicPayload())->assertStatus(429);
    }

    // ---------- sigilo da ficha ----------

    public function test_reception_editing_case_does_not_see_or_wipe_ficha(): void
    {
        $m = $this->muni();
        $rec = $this->user(Role::Recepcao, $m);
        $case = $this->makeCase($m, ['created_by' => $rec->id, 'ficha' => ['local_ocorrencia' => 'escola'], 'initial_report' => 'RELATO-X']);
        $case->child->guardians()->create(['name' => 'Tia']);

        $this->actingAs($rec)->get("/casos/{$case->id}/edit")->assertOk()->assertDontSee('RELATO-X');
        $this->actingAs($rec)->put("/casos/{$case->id}", [
            'child' => ['name' => 'Novo Nome'], 'guardians' => [['name' => 'Tia']],
            'case' => ['condition' => 'vitima', 'violence_types' => ['fisica'], 'origin' => 'CT', 'received_at' => now()->toDateString()],
        ])->assertSessionHasNoErrors();

        $fresh = $case->fresh();
        $this->assertSame('RELATO-X', $fresh->initial_report);
        $this->assertSame(['local_ocorrencia' => 'escola'], $fresh->ficha);
        $this->assertSame('Novo Nome', $fresh->child->name);
    }

    public function test_ficha_saved_by_technician_and_case_pdf_is_gated_and_audited(): void
    {
        $m = $this->muni();
        $psi = $this->user(Role::Psicologo, $m);
        $this->actingAs($psi)->post('/casos', [
            'child' => ['name' => 'Ana'], 'guardians' => [],
            'case' => ['condition' => 'vitima', 'violence_types' => ['sexual'], 'origin' => 'CT', 'received_at' => now()->toDateString()],
            'ficha' => ['vinculo_autor' => 'padrasto', 'meio_agressao' => ['ameaca'], 'faixa_autor' => 'adulta'],
        ])->assertSessionHasNoErrors();
        $case = CaseRecord::firstOrFail();
        $this->assertSame('padrasto', $case->ficha['vinculo_autor']);

        $res = $this->actingAs($psi)->get("/relatorios/caso/{$case->id}")->assertOk();
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertDatabaseHas('audit_logs', ['user_id' => $psi->id, 'action' => AuditAction::Exported->value, 'case_id' => $case->id]);

        $rec = $this->user(Role::Recepcao, $m);
        $this->actingAs($rec)->get("/relatorios/caso/{$case->id}")->assertForbidden();
    }

    // ---------- histórico da criança ----------

    public function test_new_case_for_existing_child_links_same_record(): void
    {
        $m = $this->muni();
        $psi = $this->user(Role::Psicologo, $m);
        $first = $this->makeCase($m, ['responsible_id' => $psi->id]);

        $this->actingAs($psi)->get('/atendidos?q='.mb_substr($first->child->name, 0, 6))->assertOk()->assertSee($first->child->name);
        $this->actingAs($psi)->get("/casos/create?child={$first->child_id}")->assertOk()->assertSee('já atendido');
        $this->actingAs($psi)->post('/casos', [
            'child_id' => $first->child_id, 'child' => ['name' => $first->child->name], 'guardians' => [],
            'case' => ['condition' => 'vitima', 'violence_types' => ['psicologica'], 'origin' => 'Escola', 'received_at' => now()->toDateString()],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Child::count());
        $this->assertSame(2, $first->child->cases()->count());
        $this->actingAs($psi)->get("/atendidos/{$first->child_id}")->assertOk();
    }

    public function test_cannot_link_case_to_child_from_inaccessible_case(): void
    {
        $a = $this->muni('Alfa');
        $b = $this->muni('Beta');
        $foreign = $this->makeCase($b);
        $psi = $this->user(Role::Psicologo, $a);

        $this->actingAs($psi)->get("/casos/create?child={$foreign->child_id}")->assertForbidden();
        $this->actingAs($psi)->get('/atendidos?q='.mb_substr($foreign->child->name, 0, 6))->assertDontSee($foreign->child->name);
    }

    // ---------- administração de usuários ----------

    public function test_admin_creates_user_who_must_change_password(): void
    {
        $m = $this->muni();
        $admin = $this->user(Role::Administrador);
        $admin->update(['municipality_id' => null]);

        $this->actingAs($admin)->post('/admin/usuarios', [
            'name' => 'Nova Pessoa', 'email' => 'nova@x.com', 'role' => 'psicologo', 'municipality_id' => $m->id,
            'password' => 'Provisoria123', 'active' => 1,
        ])->assertSessionHasNoErrors();
        auth()->logout();

        $this->post('/login', ['email' => 'nova@x.com', 'password' => 'Provisoria123'])->assertRedirect();
        $this->get('/casos')->assertRedirect('/senha');
        $this->put('/senha', ['current_password' => 'Provisoria123', 'password' => 'Definitiva456', 'password_confirmation' => 'Definitiva456'])
            ->assertRedirect('/');
        $this->get('/casos')->assertOk();
    }

    public function test_user_validation_rules(): void
    {
        $m = $this->muni();
        $admin = $this->user(Role::Administrador);
        $admin->update(['municipality_id' => null]);
        $this->actingAs($admin);

        $this->post('/admin/usuarios', ['name' => 'A', 'email' => 'a@x.com', 'role' => 'psicologo', 'password' => 'Provisoria123'])
            ->assertSessionHasErrors('municipality_id'); // exceto admin, município é obrigatório
        $this->post('/admin/usuarios', ['name' => 'A', 'email' => 'a@x.com', 'role' => 'psicologo', 'municipality_id' => $m->id, 'password' => 'curta'])
            ->assertSessionHasErrors('password');
        $this->put("/admin/usuarios/{$admin->id}", ['name' => 'A', 'email' => $admin->email, 'role' => 'psicologo', 'municipality_id' => $m->id, 'active' => 1])
            ->assertSessionHasErrors('role'); // não pode se auto-rebaixar
    }

    public function test_inactive_user_session_is_cut_off_from_cases(): void
    {
        $m = $this->muni();
        $psi = $this->user(Role::Psicologo, $m);
        $case = $this->makeCase($m);
        $psi->update(['active' => false]);

        $this->actingAs($psi)->get("/casos/{$case->id}")->assertForbidden();
    }

    // ---------- auditoria por município / estatísticas / alertas ----------

    public function test_coordination_audit_is_limited_to_own_municipality(): void
    {
        $a = $this->muni('Alfa');
        $b = $this->muni('Beta');
        $uA = $this->user(Role::Psicologo, $a);
        $uB = $this->user(Role::Psicologo, $b);
        $caseA = $this->makeCase($a, ['responsible_id' => $uA->id]);
        $caseB = $this->makeCase($b, ['responsible_id' => $uB->id]);
        $this->actingAs($uA)->get("/casos/{$caseA->id}");
        $this->actingAs($uB)->get("/casos/{$caseB->id}");

        $this->actingAs($this->user(Role::Coordenacao, $a))->get('/auditoria')
            ->assertSee($uA->name)->assertDontSee($uB->name);
    }

    public function test_statistics_are_aggregated_and_scoped(): void
    {
        $a = $this->muni('Alfa');
        $b = $this->muni('Beta');
        $this->makeCase($a, ['violence_types' => ['sexual', 'fisica'], 'urgent' => true, 'origin' => 'Escola XYZ']);
        $this->makeCase($a, ['violence_types' => ['fisica']]);
        $this->makeCase($b, ['violence_types' => ['negligencia']]);
        $coord = $this->user(Role::Coordenacao, $a);

        $r = $this->actingAs($coord)->get('/estatisticas')->assertOk()->assertSee('Violência física');
        $r->assertDontSee('Negligência / abandono');

        $this->actingAs($coord)->get('/estatisticas/pdf')->assertOk();
        $this->actingAs($this->user(Role::Psicologo, $a))->get('/estatisticas')->assertForbidden();
    }

    public function test_alert_command_sends_only_protocols_to_coordination_and_professionals(): void
    {
        Notification::fake();
        $m = $this->muni();
        $coord = $this->user(Role::Coordenacao, $m);
        $psi = $this->user(Role::Psicologo, $m);
        $case = $this->makeCase($m, ['received_at' => now()->subDays(10), 'responsible_id' => $psi->id]);
        $case->referrals()->create(['destination' => 'creas', 'status' => ReferralStatus::Enviado, 'due_at' => now()->subDays(2)]);
        $case->sessions()->create(['professional_id' => $psi->id, 'scheduled_at' => now()->addHours(5), 'status' => SessionStatus::Agendada]);
        $this->makeCase($m, ['received_at' => now()->subDays(10)]); // fila antiga

        $this->artisan('sigiep:alertas')->assertSuccessful();

        Notification::assertSentTo($coord, AlertasDiarios::class, function ($n) use ($case) {
            return in_array($case->protocol, $n->dados['atrasados'], true) && count($n->dados['fila']) === 1;
        });
        Notification::assertSentTo($psi, AlertasDiarios::class, fn ($n) => $n->dados['escutas'] === [$case->protocol] && $n->dados['atrasados'] === []);
    }

    public function test_alert_command_is_silent_when_nothing_is_pending(): void
    {
        Notification::fake();
        $m = $this->muni();
        $this->user(Role::Coordenacao, $m);
        $this->artisan('sigiep:alertas')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_public_form_has_same_fields_as_internal_form(): void
    {
        $m = $this->muni();
        $names = function (string $html): array {
            preg_match_all('/name="([^"]+)"/', $html, $mm);
            $n = array_map(fn ($x) => preg_replace('/\[\d+\]/', '[]', $x), $mm[1]);

            return array_values(array_unique(array_filter($n, fn ($x) => ! in_array($x, ['_token', 'website']) && ! str_starts_with($x, 'ficha[notificante') && $x !== 'ficha[vinculo_notificante]')));
        };

        $public = $names($this->get("/denuncia/{$m->slug}")->getContent());
        $psi = $this->user(Role::Psicologo, $m);
        $internal = $names($this->actingAs($psi)->get('/casos/create')->getContent());

        // Só o órgão demandante e a data de recebimento são exclusivos do interno (o servidor os define na denúncia).
        $internalOnly = ['case[origin]', 'case[origin_document]', 'case[received_at]'];
        $this->assertSame([], array_values(array_diff($internal, $public, $internalOnly)), 'Campo do formulário interno ausente no público');
        $this->assertSame([], array_values(array_diff($public, $internal)), 'Campo do público ausente no interno');
    }
}
