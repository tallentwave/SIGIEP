<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\CaseStatus;
use App\Enums\Role;
use App\Enums\SessionStatus;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\Child;
use App\Models\Municipality;
use App\Models\User;
use App\Notifications\AlertasDiarios;
use App\Notifications\CasosAtribuidos;
use App\Services\WaitingList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ListaEsperaTest extends TestCase
{
    use RefreshDatabase;

    private function muni(array $over = []): Municipality
    {
        return Municipality::factory()->create($over);
    }

    private function user(Role $role, Municipality $m, array $attrs = []): User
    {
        return User::factory()->role($role)->inMunicipality($m)->create($attrs);
    }

    private function makeCase(Municipality $m, array $attrs = []): CaseRecord
    {
        return CaseRecord::createWithProtocol([
            'municipality_id' => $m->id, 'child_id' => Child::create(['name' => 'Criança '.uniqid(), 'birth_date' => now()->subYears(8)])->id,
            'condition' => 'vitima', 'violence_types' => ['fisica'], 'origin' => 'CT', 'received_at' => now()->subDay(),
            'status' => CaseStatus::Recebido, ...$attrs,
        ]);
    }

    private function addSession(CaseRecord $case, User $pro, SessionStatus $status): void
    {
        $case->sessions()->create(['professional_id' => $pro->id, 'scheduled_at' => now()->addDays(2), 'status' => $status]);
    }

    private function protocols($collection): array
    {
        return $collection->pluck('protocol')->all();
    }

    // ===================== ordem =====================

    public function test_waiting_list_is_ordered_by_urgency_then_sexual_then_overdue_then_age(): void
    {
        $m = $this->muni(['first_listening_days' => 7]);
        $coord = $this->user(Role::Coordenacao, $m);
        $recent = $this->makeCase($m, ['received_at' => now()->subDay()]);
        $overdueOld = $this->makeCase($m, ['received_at' => now()->subDays(20)]);
        $overdueNewer = $this->makeCase($m, ['received_at' => now()->subDays(9)]);
        $sexual = $this->makeCase($m, ['received_at' => now()->subDays(2), 'violence_types' => ['sexual']]);
        $urgent = $this->makeCase($m, ['received_at' => now()->subDay(), 'urgent' => true]);

        $order = $this->protocols((new WaitingList)->cases($coord));

        $this->assertSame([$urgent->protocol, $sexual->protocol, $overdueOld->protocol, $overdueNewer->protocol, $recent->protocol], $order);
    }

    public function test_urgent_beats_everything_even_sexual_overdue_cases(): void
    {
        $m = $this->muni();
        $coord = $this->user(Role::Coordenacao, $m);
        $sexualOverdue = $this->makeCase($m, ['received_at' => now()->subDays(30), 'violence_types' => ['sexual']]);
        $urgent = $this->makeCase($m, ['received_at' => now(), 'urgent' => true]);

        $this->assertSame([$urgent->protocol, $sexualOverdue->protocol], $this->protocols((new WaitingList)->cases($coord)));
    }

    public function test_reasons_wait_days_and_due_date_follow_the_municipality_goal(): void
    {
        $m = $this->muni(['first_listening_days' => 3]);
        $case = $this->makeCase($m, ['received_at' => now()->subDays(5), 'urgent' => true, 'violence_types' => ['sexual'], 'source' => 'publica']);
        $list = new WaitingList;

        $this->assertSame(['Urgente', 'Violência sexual', 'Meta vencida', 'Denúncia pública'], $list->reasons($case->load('municipality')));
        $this->assertSame(5, $list->waitingDays($case));
        $this->assertSame(now()->subDays(2)->toDateString(), $list->dueDate($case)->toDateString());

        $m->update(['first_listening_days' => 10]); // meta maior: deixa de estar vencido
        $this->assertFalse($list->overdue($case->fresh()->load('municipality')));
    }

    // ===================== quem entra =====================

    public function test_only_open_cases_without_scheduled_or_performed_listening_wait(): void
    {
        $m = $this->muni();
        $coord = $this->user(Role::Coordenacao, $m);
        $pro = $this->user(Role::Psicologo, $m);
        $waiting = $this->makeCase($m);
        $scheduled = $this->makeCase($m);
        $done = $this->makeCase($m);
        $noShow = $this->makeCase($m);
        $closed = $this->makeCase($m, ['status' => CaseStatus::Encerrado, 'closed_at' => now()]);
        $anon = $this->makeCase($m, ['anonymized_at' => now()]);
        $this->addSession($scheduled, $pro, SessionStatus::Agendada);
        $this->addSession($done, $pro, SessionStatus::Realizada);
        $this->addSession($noShow, $pro, SessionStatus::Faltou);   // faltou: continua esperando
        $this->addSession($noShow, $pro, SessionStatus::Cancelada);

        $list = $this->protocols((new WaitingList)->cases($coord));

        $this->assertEqualsCanonicalizing([$waiting->protocol, $noShow->protocol], $list);
        $this->assertNotContains($closed->protocol, $list);
        $this->assertNotContains($anon->protocol, $list);
    }

    public function test_scheduling_a_listening_removes_the_case_from_the_list(): void
    {
        $m = $this->muni();
        $coord = $this->user(Role::Coordenacao, $m);
        $pro = $this->user(Role::Psicologo, $m);
        $case = $this->makeCase($m, ['responsible_id' => $pro->id]);

        $this->actingAs($coord)->get('/fila')->assertSee($case->protocol);
        $this->post("/casos/{$case->id}/escutas", ['professional_id' => $pro->id, 'scheduled_at' => now()->addDays(3)->format('Y-m-d H:i'), 'duration_minutes' => 60])
            ->assertSessionHasNoErrors();
        $this->get('/fila')->assertDontSee($case->protocol);
    }

    public function test_filters(): void
    {
        $m = $this->muni(['first_listening_days' => 7]);
        $coord = $this->user(Role::Coordenacao, $m);
        $pro = $this->user(Role::Psicologo, $m);
        $queue = $this->makeCase($m);
        $assigned = $this->makeCase($m, ['responsible_id' => $pro->id]);
        $late = $this->makeCase($m, ['received_at' => now()->subDays(15), 'responsible_id' => $pro->id]);
        $list = new WaitingList;

        $this->assertSame([$queue->protocol], $this->protocols($list->cases($coord, 'sem_responsavel')));
        $this->assertEqualsCanonicalizing([$assigned->protocol, $late->protocol], $this->protocols($list->cases($coord, 'sem_escuta')));
        $this->assertSame([$late->protocol], $this->protocols($list->cases($coord, 'atrasados')));
        $this->assertCount(3, $list->cases($coord, 'todos'));
        $this->actingAs($coord)->get('/fila?filtro=lixo')->assertOk(); // filtro inválido cai em "todos"
    }

    // ===================== visibilidade =====================

    public function test_visibility_follows_case_access_rules_and_municipality(): void
    {
        $a = $this->muni();
        $b = $this->muni();
        $mine = $this->user(Role::Psicologo, $a);
        $other = $this->user(Role::Psicologo, $a);
        $queue = $this->makeCase($a);
        $ownCase = $this->makeCase($a, ['responsible_id' => $mine->id]);
        $others = $this->makeCase($a, ['responsible_id' => $other->id]);
        $foreign = $this->makeCase($b);

        $this->actingAs($mine)->get('/fila')->assertOk()
            ->assertSee($queue->protocol)->assertSee($ownCase->protocol)->assertDontSee($others->protocol)->assertDontSee($foreign->protocol);
        $this->actingAs($this->user(Role::Recepcao, $a))->get('/fila')->assertOk()->assertSee($queue->protocol)->assertDontSee($others->protocol);
        $this->actingAs($this->user(Role::Coordenacao, $a))->get('/fila')->assertSee($others->protocol)->assertDontSee($foreign->protocol);

        $admin = User::factory()->role(Role::Administrador)->create(['municipality_id' => null]);
        $this->actingAs($admin)->get('/fila')->assertForbidden();
        auth()->logout();
        $this->flushSession();
        $this->get('/fila')->assertRedirect('/login');
    }

    public function test_page_shows_workload_and_bulk_form_to_coordination_but_claim_button_to_professionals(): void
    {
        $m = $this->muni();
        $coord = $this->user(Role::Coordenacao, $m);
        $pro = $this->user(Role::Psicologo, $m, ['name' => 'Dra. Carga']);
        $this->makeCase($m, ['urgent' => true]);
        $this->makeCase($m, ['responsible_id' => $pro->id]);

        $this->actingAs($coord)->get('/fila')->assertSee('Carga da equipe')->assertSee('Dra. Carga')->assertSee('Atribuir selecionados')->assertSee('Urgente');
        $this->actingAs($pro)->get('/fila')->assertDontSee('Carga da equipe')->assertDontSee('Atribuir selecionados')->assertSee('Assumir');
        $this->actingAs($this->user(Role::Recepcao, $m))->get('/fila')->assertDontSee('Assumir')->assertDontSee('Atribuir selecionados');
    }

    public function test_workload_counts(): void
    {
        $m = $this->muni();
        $pro = $this->user(Role::Psicologo, $m);
        $idle = $this->user(Role::AssistenteSocial, $m);
        $this->user(Role::Recepcao, $m); // não entra na carga
        $withSession = $this->makeCase($m, ['responsible_id' => $pro->id]);
        $this->addSession($withSession, $pro, SessionStatus::Agendada);
        $this->makeCase($m, ['responsible_id' => $pro->id]);                                   // aberto e aguardando
        $this->makeCase($m, ['responsible_id' => $pro->id, 'status' => CaseStatus::Encerrado, 'closed_at' => now()]); // não conta

        $w = (new WaitingList)->workload($m->id)->keyBy(fn ($r) => $r['user']->id);

        $this->assertCount(2, $w);
        $this->assertSame(['open' => 2, 'waiting' => 1, 'upcoming' => 1], collect($w[$pro->id])->only(['open', 'waiting', 'upcoming'])->all());
        $this->assertSame(['open' => 0, 'waiting' => 0, 'upcoming' => 0], collect($w[$idle->id])->only(['open', 'waiting', 'upcoming'])->all());
    }

    // ===================== atribuição em lote =====================

    public function test_coordination_assigns_in_bulk_with_audit_and_protocol_only_email(): void
    {
        Notification::fake();
        $m = $this->muni();
        $coord = $this->user(Role::Coordenacao, $m);
        $pro = $this->user(Role::Psicologo, $m);
        $a = $this->makeCase($m);
        $b = $this->makeCase($m);

        $this->actingAs($coord)->post('/fila/atribuir', ['ids' => [$a->id, $b->id], 'responsible_id' => $pro->id])
            ->assertSessionHas('ok', '2 caso(s) atribuído(s).');

        $this->assertSame($pro->id, $a->fresh()->responsible_id);
        $this->assertSame($pro->id, $b->fresh()->responsible_id);
        $this->assertSame(2, AuditLog::where('action', AuditAction::Assigned->value)->where('user_id', $coord->id)->count());
        Notification::assertSentTo($pro, CasosAtribuidos::class, fn ($n) => $n->protocolos === [$a->protocol, $b->protocol]);

        // O profissional passa a ter acesso ao caso (continua na lista até ter escuta marcada).
        $this->actingAs($pro)->get("/casos/{$a->id}")->assertOk();
    }

    public function test_reassigning_moves_the_case_and_same_professional_is_skipped(): void
    {
        Notification::fake();
        $m = $this->muni();
        $coord = $this->user(Role::Coordenacao, $m);
        $old = $this->user(Role::Psicologo, $m);
        $new = $this->user(Role::AssistenteSocial, $m);
        $case = $this->makeCase($m, ['responsible_id' => $old->id]);

        $this->actingAs($coord)->post('/fila/atribuir', ['ids' => [$case->id], 'responsible_id' => $new->id])->assertSessionHas('ok');
        $this->assertSame($new->id, $case->fresh()->responsible_id);
        $this->actingAs($old)->get("/casos/{$case->id}")->assertForbidden(); // quem perdeu o caso perde o acesso

        $this->actingAs($coord)->post('/fila/atribuir', ['ids' => [$case->id], 'responsible_id' => $new->id])
            ->assertSessionHas('ok', fn ($m) => str_contains($m, 'Nenhum caso foi alterado'));
        Notification::assertSentToTimes($new, CasosAtribuidos::class, 1);
    }

    public function test_assignment_rejects_invalid_professionals_and_foreign_or_anonymized_cases(): void
    {
        Notification::fake();
        $a = $this->muni();
        $b = $this->muni();
        $coord = $this->user(Role::Coordenacao, $a);
        $pro = $this->user(Role::Psicologo, $a);
        $case = $this->makeCase($a);
        $foreignCase = $this->makeCase($b);
        $anon = $this->makeCase($a, ['anonymized_at' => now()]);
        $this->actingAs($coord);

        foreach ([$this->user(Role::Psicologo, $b), $this->user(Role::Recepcao, $a), $this->user(Role::Coordenacao, $a), $this->user(Role::Psicologo, $a, ['active' => false])] as $bad) {
            $this->post('/fila/atribuir', ['ids' => [$case->id], 'responsible_id' => $bad->id])->assertSessionHasErrors('responsible_id');
        }
        $this->post('/fila/atribuir', ['ids' => [], 'responsible_id' => $pro->id])->assertSessionHasErrors('ids');
        $this->assertNull($case->fresh()->responsible_id);

        $this->post('/fila/atribuir', ['ids' => [$foreignCase->id, $anon->id], 'responsible_id' => $pro->id])
            ->assertSessionHas('ok', fn ($m) => str_contains($m, 'Nenhum caso foi alterado'));
        $this->assertNull($foreignCase->fresh()->responsible_id);
        $this->assertNull($anon->fresh()->responsible_id);
        Notification::assertNothingSent();
    }

    public function test_only_coordination_can_assign(): void
    {
        $m = $this->muni();
        $pro = $this->user(Role::Psicologo, $m);
        $case = $this->makeCase($m);

        foreach ([$pro, $this->user(Role::AssistenteSocial, $m), $this->user(Role::Recepcao, $m)] as $u) {
            $this->actingAs($u)->post('/fila/atribuir', ['ids' => [$case->id], 'responsible_id' => $pro->id])->assertForbidden();
        }
        $this->assertNull($case->fresh()->responsible_id);
    }

    // ===================== alertas e meta =====================

    public function test_daily_alert_lists_cases_past_the_first_listening_goal(): void
    {
        Notification::fake();
        $m = $this->muni(['first_listening_days' => 5]);
        $coord = $this->user(Role::Coordenacao, $m);
        $late = $this->makeCase($m, ['received_at' => now()->subDays(8)]);
        $this->makeCase($m, ['received_at' => now()->subDays(2)]); // dentro da meta
        $scheduled = $this->makeCase($m, ['received_at' => now()->subDays(9), 'responsible_id' => $this->user(Role::Psicologo, $m)->id]);
        $this->addSession($scheduled, $scheduled->responsible, SessionStatus::Agendada); // já tem escuta

        $this->artisan('sigiep:alertas')->assertSuccessful();

        Notification::assertSentTo($coord, AlertasDiarios::class, fn ($n) => $n->dados['espera'] === [$late->protocol]);
    }

    public function test_goal_is_configured_on_the_deadlines_screen(): void
    {
        $m = $this->muni();
        $coord = $this->user(Role::Coordenacao, $m);

        $this->actingAs($coord)->get("/admin/municipios/{$m->id}/prazos")->assertSee('Meta: dias até a 1ª escuta');
        $this->put("/admin/municipios/{$m->id}/prazos", ['sinan_deadline_sexual_days' => 1, 'sinan_deadline_default_days' => 7, 'queue_alert_days' => 3, 'first_listening_days' => 0])
            ->assertSessionHasErrors('first_listening_days');
        $this->put("/admin/municipios/{$m->id}/prazos", ['sinan_deadline_sexual_days' => 1, 'sinan_deadline_default_days' => 7, 'queue_alert_days' => 3, 'first_listening_days' => 10])
            ->assertSessionHasNoErrors();
        $this->assertSame(10, $m->fresh()->first_listening_days);
    }
}
