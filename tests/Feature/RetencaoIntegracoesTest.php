<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\CaseStatus;
use App\Enums\DispatchStatus;
use App\Enums\Role;
use App\Enums\SessionStatus;
use App\Http\Controllers\StatsController;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\Child;
use App\Models\Municipality;
use App\Models\User;
use App\Notifications\AlertasDiarios;
use App\Services\SinanExporter;
use App\Support\CnjNumber;
use App\Support\Ibge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RetencaoIntegracoesTest extends TestCase
{
    use RefreshDatabase;

    private const CNJ = '0002080-25.2012.5.15.0049';

    private function muni(array $over = []): Municipality
    {
        return Municipality::factory()->create(['ibge_code' => '3550308', 'sinan_unit_type' => 2,
            'sinan_unit_name' => 'CREAS Teste', 'sinan_unit_code' => '123', ...$over]);
    }

    private function user(Role $role, Municipality $m): User
    {
        return User::factory()->role($role)->inMunicipality($m)->create();
    }

    private function makeCase(Municipality $m, array $attrs = [], ?Child $child = null): CaseRecord
    {
        $child ??= Child::create(['name' => 'Maria Secreta', 'mother_name' => 'Mae Secreta', 'address' => 'Rua Secreta 1',
            'neighborhood' => 'Bairro Secreto', 'cpf' => '111.222.333-44', 'sex' => 'Feminino', 'birth_date' => now()->subYears(8)->subDays(10)]);

        return CaseRecord::create([
            'protocol' => CaseRecord::nextProtocol(), 'municipality_id' => $m->id, 'child_id' => $child->id,
            'condition' => 'vitima', 'violence_types' => ['fisica'], 'origin' => 'CT', 'received_at' => now()->subYears(1),
            'violence_date' => now()->subYears(1)->subDay(), 'status' => CaseStatus::Encerrado, 'closed_at' => now()->subYears(11),
            'initial_report' => 'RELATO-SECRETO', 'alleged_author' => 'AUTOR-SECRETO', 'origin_document' => 'OF-123', ...$attrs,
        ]);
    }

    private function fullCase(Municipality $m): CaseRecord
    {
        $psi = $this->user(Role::Psicologo, $m);
        $case = $this->makeCase($m, ['ficha' => [
            'local_ocorrencia' => 'residencia', 'vinculo_autor' => 'padrasto', 'autor_descricao' => 'AUTOR-DESC-SECRETA',
            'notificante_nome' => 'NOTIFICANTE-SECRETO', 'notificante_telefone' => '9999', 'vinculo_notificante' => 'vizinho',
        ]]);
        $case->child->guardians()->create(['name' => 'Responsavel Secreto', 'phone' => '1199']);
        $case->sessions()->create(['professional_id' => $psi->id, 'scheduled_at' => now()->subYears(10), 'status' => SessionStatus::Realizada,
            'report' => 'RELATO-ESCUTA-SECRETO', 'technical_notes' => 'NOTA-SECRETA', 'accompanied_by' => 'Tia Secreta', 'room' => 'Sala 9']);
        $case->referrals()->create(['destination' => 'creas', 'status' => 'enviado', 'summary' => 'RESUMO-SECRETO', 'return_notes' => 'RETORNO-SECRETO', 'document_number' => 'OF-9']);
        $case->processes()->create(['number' => self::CNJ]);

        return $case;
    }

    // ===================== RETENÇÃO =====================

    public function test_anonymization_removes_identifying_data_but_keeps_statistics(): void
    {
        $m = $this->muni();
        $case = $this->fullCase($m);

        $this->artisan('sigiep:retencao')->assertSuccessful();

        $case = CaseRecord::find($case->id);
        $this->assertNotNull($case->anonymized_at);
        $this->assertSame('prazo', $case->anonymization_reason);
        $this->assertNull($case->initial_report);
        $this->assertNull($case->alleged_author);
        $this->assertNull($case->origin_document);
        // Ficha: categorias ficam; texto livre e notificante saem.
        $this->assertSame(['local_ocorrencia' => 'residencia', 'vinculo_autor' => 'padrasto'], $case->ficha);
        // Estatística preservada.
        $this->assertSame(['fisica'], $case->violence_types);
        $this->assertNotNull($case->violence_date);

        $s = $case->sessions()->first();
        $this->assertNull($s->report);
        $this->assertNull($s->technical_notes);
        $this->assertNull($s->accompanied_by);
        $this->assertNull($s->room);
        $r = $case->referrals()->first();
        $this->assertNull($r->summary);
        $this->assertNull($r->return_notes);
        $this->assertNull($r->document_number);
        $this->assertSame(0, $case->processes()->count());

        $child = Child::find($case->child_id);
        $this->assertSame('Anonimizado', $child->name);
        $this->assertSame(0, $child->guardians()->count());
        $this->assertSame(now()->subYears(8)->subDays(10)->year.'-01-01', $child->birth_date->toDateString());

        // Nada identificável sobra nas colunas em texto puro.
        foreach (['children', 'guardians', 'cases', 'listening_sessions', 'referrals'] as $t) {
            $this->assertStringNotContainsString('Secret', json_encode(DB::table($t)->get()), "vazamento em {$t}");
        }
        $log = AuditLog::where('action', AuditAction::Anonymized->value)->firstOrFail();
        $this->assertSame($case->id, $log->case_id);
        $this->assertSame(['reason' => 'prazo'], $log->changes);
    }

    public function test_only_expired_unheld_closed_cases_are_eligible(): void
    {
        $m = $this->muni();
        $expired = $this->makeCase($m);
        $recent = $this->makeCase($m, ['closed_at' => now()->subYears(5)]);
        $open = $this->makeCase($m, ['status' => CaseStatus::EmEscuta, 'closed_at' => null]);
        $held = $this->makeCase($m, ['legal_hold' => true, 'legal_hold_reason' => 'Processo em curso']);

        $this->artisan('sigiep:retencao')->assertSuccessful();

        $this->assertNotNull($expired->fresh()->anonymized_at);
        foreach ([$recent, $open, $held] as $c) {
            $this->assertNull($c->fresh()->anonymized_at, $c->protocol);
        }
        $this->assertSame('Maria Secreta', $held->child->fresh()->name);
    }

    public function test_dry_run_changes_nothing_and_municipal_retention_period_is_respected(): void
    {
        $short = $this->muni(['retention_years' => 3]);
        $long = $this->muni(['retention_years' => 20, 'ibge_code' => '3304557']);
        $a = $this->makeCase($short, ['closed_at' => now()->subYears(4)]);
        $b = $this->makeCase($long, ['closed_at' => now()->subYears(4)]);

        $this->artisan('sigiep:retencao --dry-run')->assertSuccessful();
        $this->assertNull($a->fresh()->anonymized_at);

        $this->artisan('sigiep:retencao')->assertSuccessful();
        $this->assertNotNull($a->fresh()->anonymized_at);
        $this->assertNull($b->fresh()->anonymized_at);

        $this->artisan('sigiep:retencao --municipio='.$short->id)->assertSuccessful(); // idempotente
    }

    public function test_child_is_anonymized_only_after_all_their_cases_are(): void
    {
        $m = $this->muni();
        $child = Child::create(['name' => 'Joao Secreto']);
        $old = $this->makeCase($m, [], $child);
        $recent = $this->makeCase($m, ['closed_at' => now()->subYears(2)], $child);

        $this->artisan('sigiep:retencao');
        $this->assertNotNull($old->fresh()->anonymized_at);
        $this->assertSame('Joao Secreto', $child->fresh()->name); // ainda há caso vivo

        $recent->update(['closed_at' => now()->subYears(11)]);
        $this->artisan('sigiep:retencao');
        $this->assertSame('Anonimizado', $child->fresh()->name);
    }

    public function test_trashed_cases_are_anonymized_after_grace_period(): void
    {
        $m = $this->muni();
        $fresh = $this->makeCase($m, ['status' => CaseStatus::EmEscuta, 'closed_at' => null]);
        $stale = $this->makeCase($m, ['status' => CaseStatus::EmEscuta, 'closed_at' => null]);
        $fresh->delete();
        $stale->delete();
        $stale->forceFill(['deleted_at' => now()->subDays(91)])->saveQuietly();

        $this->artisan('sigiep:retencao')->assertSuccessful();

        $this->assertNull(CaseRecord::withTrashed()->find($fresh->id)->anonymized_at);
        $stale = CaseRecord::withTrashed()->find($stale->id);
        $this->assertNotNull($stale->anonymized_at);
        $this->assertSame('exclusao', $stale->anonymization_reason);
    }

    public function test_old_audit_trail_is_pruned_and_the_pruning_is_recorded(): void
    {
        DB::table('audit_logs')->insert([
            ['action' => 'login', 'user_name' => 'velho', 'created_at' => now()->subYears(6)],
            ['action' => 'login', 'user_name' => 'novo', 'created_at' => now()->subYear()],
        ]);

        $this->artisan('sigiep:retencao')->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['user_name' => 'velho']);
        $this->assertDatabaseHas('audit_logs', ['user_name' => 'novo']);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::AuditPruned->value]);
    }

    public function test_anonymized_case_is_read_only_but_still_counts_in_statistics(): void
    {
        $m = $this->muni();
        $coord = $this->user(Role::Coordenacao, $m);
        $case = $this->makeCase($m, ['violence_types' => ['sexual'], 'received_at' => now()->subDay()]);
        $this->artisan('sigiep:retencao');

        $this->actingAs($coord)->get("/casos/{$case->id}")->assertOk()->assertSee('Caso anonimizado')->assertDontSee('Maria Secreta');
        $this->put("/casos/{$case->id}", ['child' => ['name' => 'X'], 'guardians' => [],
            'case' => ['condition' => 'vitima', 'violence_types' => ['fisica'], 'origin' => 'CT', 'received_at' => now()->toDateString()]])->assertForbidden();
        $this->put("/casos/{$case->id}/gestao", ['status' => 'recebido'])->assertForbidden();
        $this->post("/casos/{$case->id}/encaminhamentos", ['destination' => 'creas'])->assertForbidden();
        $this->get('/estatisticas')->assertOk()->assertSee('Violência sexual');
        $this->assertSame(1, app(StatsController::class)->data(request()->duplicate(['de' => now()->subYear()->toDateString()], null))['byType']['Violência sexual'] ?? 0);
    }

    public function test_legal_hold_blocks_anonymization_until_lifted(): void
    {
        $m = $this->muni();
        $coord = $this->user(Role::Coordenacao, $m);
        $case = $this->makeCase($m);

        $this->actingAs($coord)->post("/casos/{$case->id}/guarda-legal", ['reason' => 'curta'])->assertSessionHasErrors('reason');
        $this->post("/casos/{$case->id}/guarda-legal", ['reason' => 'Ação penal em curso, requisição do MP'])->assertSessionHasNoErrors();
        $this->assertTrue($case->fresh()->legal_hold);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $coord->id, 'action' => AuditAction::LegalHoldSet->value]);

        $this->artisan('sigiep:retencao');
        $this->assertNull($case->fresh()->anonymized_at);

        $this->post("/casos/{$case->id}/guarda-legal")->assertSessionHasNoErrors();
        $this->assertFalse($case->fresh()->legal_hold);
        $this->artisan('sigiep:retencao');
        $this->assertNotNull($case->fresh()->anonymized_at);
    }

    public function test_manual_anonymization_requires_closed_case_confirmation_and_no_hold(): void
    {
        $m = $this->muni();
        $coord = $this->user(Role::Coordenacao, $m);
        $recent = $this->makeCase($m, ['closed_at' => now()->subMonth()]);
        $open = $this->makeCase($m, ['status' => CaseStatus::EmEscuta, 'closed_at' => null]);
        $held = $this->makeCase($m, ['closed_at' => now()->subMonth(), 'legal_hold' => true]);
        $this->actingAs($coord);
        $body = fn ($c, $confirm = null) => ['reason' => 'Pedido da família, caso encerrado', 'confirm' => $confirm ?? $c->protocol];

        $this->post("/casos/{$recent->id}/anonimizar", $body($recent, 'ERRADO'))->assertSessionHasErrors('confirm');
        $this->post("/casos/{$open->id}/anonimizar", $body($open))->assertSessionHasErrors('reason');
        $this->post("/casos/{$held->id}/anonimizar", $body($held))->assertSessionHasErrors('reason');
        $this->assertNull($recent->fresh()->anonymized_at);

        $this->post("/casos/{$recent->id}/anonimizar", $body($recent))->assertRedirect("/casos/{$recent->id}");
        $this->assertSame('manual', $recent->fresh()->anonymization_reason);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $coord->id, 'action' => AuditAction::Anonymized->value, 'case_id' => $recent->id]);
    }

    public function test_retention_permissions_and_municipal_isolation(): void
    {
        $a = $this->muni();
        $b = $this->muni(['ibge_code' => '3304557']);
        $caseB = $this->makeCase($b);
        $admin = User::factory()->role(Role::Administrador)->create(['municipality_id' => null]);

        $this->actingAs($this->user(Role::Coordenacao, $a))->get('/retencao')->assertOk();
        $this->post("/casos/{$caseB->id}/guarda-legal", ['reason' => 'Motivo qualquer válido'])->assertForbidden();
        $this->post("/casos/{$caseB->id}/anonimizar", ['reason' => 'Motivo qualquer válido', 'confirm' => $caseB->protocol])->assertForbidden();
        $this->actingAs($this->user(Role::Psicologo, $a))->get('/retencao')->assertForbidden();
        $this->actingAs($admin)->get('/retencao')->assertOk();
        $this->actingAs($admin)->post("/casos/{$caseB->id}/guarda-legal", ['reason' => 'Motivo qualquer válido'])->assertForbidden();
    }

    // ===================== CNJ =====================

    public function test_cnj_number_validation(): void
    {
        $this->assertTrue(CnjNumber::isValid(self::CNJ));
        $this->assertTrue(CnjNumber::isValid('00020802520125150049')); // sem pontuação
        $this->assertSame(self::CNJ, CnjNumber::format('00020802520125150049'));
        $this->assertFalse(CnjNumber::isValid('0002080-26.2012.5.15.0049')); // DV errado
        $this->assertFalse(CnjNumber::isValid('0002081-25.2012.5.15.0049')); // número adulterado
        $this->assertFalse(CnjNumber::isValid('123'));
        $this->assertSame('Justiça do Trabalho', CnjNumber::court(self::CNJ));
        $tj = CnjNumber::checkDigit('0001234', '2023', '8', '26', '0100');
        $this->assertSame('Justiça Estadual · TJ26→SP', CnjNumber::court(sprintf('0001234-%02d.2023.8.26.0100', $tj)));
    }

    public function test_link_process_validates_number_and_blocks_duplicates_and_outsiders(): void
    {
        $m = $this->muni();
        $psi = $this->user(Role::Psicologo, $m);
        $case = $this->makeCase($m, ['responsible_id' => $psi->id, 'status' => CaseStatus::EmEscuta, 'closed_at' => null]);

        $this->actingAs($psi)->post("/casos/{$case->id}/processos", ['number' => '0002080-26.2012.5.15.0049'])->assertSessionHasErrors('number');
        $this->post("/casos/{$case->id}/processos", ['number' => '00020802520125150049', 'court_unit' => '1ª Vara'])->assertSessionHasNoErrors();
        $this->assertSame(self::CNJ, $case->processes()->first()->number);
        $this->post("/casos/{$case->id}/processos", ['number' => self::CNJ])->assertSessionHasErrors('number');

        $this->delete('/processos/'.$case->processes()->first()->id)->assertSessionHasNoErrors();
        $this->assertSame(0, $case->processes()->count());

        $rec = $this->user(Role::Recepcao, $m);
        $this->actingAs($rec)->post("/casos/{$case->id}/processos", ['number' => self::CNJ])->assertForbidden();
    }

    public function test_justice_communication_has_minimum_content_and_is_registered(): void
    {
        $m = $this->muni();
        $psi = $this->user(Role::Psicologo, $m);
        $case = $this->makeCase($m, ['responsible_id' => $psi->id, 'status' => CaseStatus::EmEscuta, 'closed_at' => null]);
        $case->sessions()->create(['professional_id' => $psi->id, 'scheduled_at' => now()->subDay(), 'status' => SessionStatus::Realizada,
            'report' => 'RELATO-ESCUTA-SECRETO', 'technical_notes' => 'NOTA-SECRETA']);
        $proc = $case->processes()->create(['number' => self::CNJ]);

        $this->actingAs($psi)->post("/casos/{$case->id}/justica/comunicacao", ['summary' => 'curta'])->assertSessionHasErrors('summary');
        $other = $this->makeCase($m);
        $foreign = $other->processes()->create(['number' => self::CNJ]);
        $this->post("/casos/{$case->id}/justica/comunicacao", ['summary' => str_repeat('Síntese técnica. ', 3), 'judicial_process_id' => $foreign->id])
            ->assertSessionHasErrors('judicial_process_id');

        $res = $this->post("/casos/{$case->id}/justica/comunicacao", ['summary' => 'SINTESE-DO-PROFISSIONAL com informações necessárias.', 'judicial_process_id' => $proc->id])->assertOk();
        $this->assertStringStartsWith('%PDF', $res->getContent());

        $d = $case->dispatches()->firstOrFail();
        $this->assertSame(DispatchStatus::Gerado, $d->status);
        $this->assertStringNotContainsString('SINTESE', DB::table('integration_dispatches')->value('summary'));

        $html = view('relatorios.justica_pdf', ['case' => $case->load('child', 'municipality', 'referrals', 'sessions.professional'), 'process' => $proc,
            'summary' => 'SINTESE-DO-PROFISSIONAL', 'recipient' => null, 'author' => $psi, 'dispatch' => $d, 'types' => 'x'])->render();
        $this->assertStringContainsString('SINTESE-DO-PROFISSIONAL', $html);
        $this->assertStringContainsString(self::CNJ, $html);
        $this->assertStringNotContainsString('RELATO-ESCUTA-SECRETO', $html);
        $this->assertStringNotContainsString('NOTA-SECRETA', $html);

        $this->post("/integracoes/{$d->id}/concluir", ['reference' => 'Of. 55/2026', 'sent_at' => now()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame(DispatchStatus::Concluido, $d->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $psi->id, 'action' => AuditAction::IntegrationConfirmed->value]);
    }

    // ===================== SINAN =====================

    private function sinanCase(Municipality $m, array $ficha = [], array $over = []): CaseRecord
    {
        $violence = now()->subDays(20);

        return $this->makeCase($m, [
            'status' => CaseStatus::EmEscuta, 'closed_at' => null, 'received_at' => now()->subDays(10), 'violence_date' => $violence,
            'violence_types' => ['sexual', 'psicologica'],
            'ficha' => $ficha ?: [
                'local_ocorrencia' => 'residencia', 'zona' => 'urbana', 'ocorreu_outras_vezes' => 'sim', 'raca_cor' => 'parda',
                'meio_agressao' => ['ameaca'], 'tipo_sexual' => ['estupro'], 'vinculo_autor' => 'padrasto',
                'sexo_autor' => 'masculino', 'faixa_autor' => 'adulta', 'suspeita_alcool' => 'nao', 'num_envolvidos' => 'um',
            ], ...$over,
        ], Child::create(['name' => 'Maria Teste', 'sex' => 'Feminino', 'birth_date' => $violence->copy()->subYears(8)->subMonths(2), 'mother_name' => 'Ana', 'address' => 'Rua A', 'neighborhood' => 'Centro']));
    }

    public function test_sinan_mapping_uses_official_field_names_and_codes(): void
    {
        $m = $this->muni(['uf' => 'SP']);
        $case = $this->sinanCase($m);
        $case->referrals()->createMany([
            ['destination' => 'conselho_tutelar', 'status' => 'enviado'], ['destination' => 'saude', 'status' => 'pendente'],
        ]);

        $r = (new SinanExporter)->map($case);
        $row = $r['row'];

        $this->assertSame([], $r['errors']);
        $this->assertSame(['2', 'Y09', 'SP', '355030', '2', 'CREAS Teste', '123'], [$row['TP_NOT'], $row['ID_AGRAVO'], $row['SG_UF_NOT'], $row['ID_MUNICIP'], $row['TP_UNI_EXT'], $row['NM_UNI_EXT'], $row['CO_UNI_EXT']]);
        $this->assertSame('Maria Teste', $row['NM_PACIENT']);
        $this->assertSame('F', $row['CS_SEXO']);
        $this->assertSame('4008', $row['NU_IDADE_N']);          // 8 anos na data do fato
        $this->assertSame('4', $row['CS_RACA']);                  // parda
        $this->assertSame('01', $row['LOCAL_OCOR']);              // residência
        $this->assertSame('1', $row['ZONA_OCOR']);
        $this->assertSame('1', $row['OUT_VEZES']);
        // Tipos de violência (campo 56): marcados = 1, demais = 2
        $this->assertSame(['2', '1', '1', '2'], [$row['VIOL_FISIC'], $row['VIOL_PSICO'], $row['VIOL_SEXU'], $row['VIOL_NEGLI']]);
        $this->assertSame(['1', '2'], [$row['AG_AMEACA'], $row['AG_FORCA']]);
        $this->assertSame(['1', '2', '2'], [$row['SEX_ESTUPR'], $row['SEX_ASSEDI'], $row['SEX_PORNO']]);
        // Autor
        $this->assertSame(['1', '2', '2', '2'], [$row['REL_PAD'], $row['REL_PAI'], $row['REL_MAE'], $row['REL_DESCO']]);
        $this->assertSame(['1', '2', '1', '4'], [$row['NUM_ENVOL'], $row['AUTOR_ALCO'], $row['AUTOR_SEXO'], $row['CICL_VID_AU']]);
        // Encaminhamentos: existentes = 1, demais = 2
        $this->assertSame(['1', '1', '2', '2'], [$row['CONS_TUTEL'], $row['ENC_SAUDE'], $row['ASSIST_SOC'], $row['INFAN_JUV']]);
        $this->assertSame('', $row['NU_NOTIFIC']);
        $this->assertSame(array_keys($row), SinanExporter::COLUMNS);
    }

    public function test_sinan_uses_ignored_codes_and_warns_when_ficha_is_missing(): void
    {
        $m = $this->muni();
        $case = $this->sinanCase($m, ['zona' => 'rural'], ['violence_types' => ['fisica']]);

        $r = (new SinanExporter)->map($case);
        $row = $r['row'];

        $this->assertSame([], $r['errors']);
        $this->assertSame('99', $row['LOCAL_OCOR']);
        $this->assertSame('2', $row['ZONA_OCOR']);
        $this->assertSame(['9', '9'], [$row['AG_FORCA'], $row['REL_PAI']]);
        $this->assertSame('8', $row['SEX_ESTUPR']);          // não houve violência sexual → 8 (não se aplica)
        $this->assertSame('9', $row['CONS_TUTEL']);          // sem encaminhamentos ainda
        $this->assertNotEmpty(array_filter($r['warnings'], fn ($w) => str_contains($w, 'LOCAL_OCOR')));
        $this->assertNotEmpty(array_filter($r['warnings'], fn ($w) => str_contains($w, 'REL_*')));
    }

    public function test_sinan_blocks_export_when_mandatory_data_is_missing(): void
    {
        $m = $this->muni(['ibge_code' => null, 'sinan_unit_name' => null]);
        $case = $this->sinanCase($m, [], ['violence_date' => null]);
        $case->child->update(['birth_date' => null]);

        $errors = (new SinanExporter)->map($case->fresh())['errors'];
        $this->assertCount(4, $errors);

        $psi = $this->user(Role::Psicologo, $m);
        $case->update(['responsible_id' => $psi->id]);
        $this->actingAs($psi)->post('/integracoes/sinan/arquivo', ['ids' => [$case->id]])->assertSessionHasErrors();
        $this->assertSame(0, $case->dispatches()->count());
    }

    public function test_sinan_file_download_records_dispatch_and_audit_and_neutralizes_formulas(): void
    {
        $m = $this->muni();
        $psi = $this->user(Role::Psicologo, $m);
        $case = $this->sinanCase($m, [], ['responsible_id' => $psi->id]);
        $case->child->update(['name' => '=HYPERLINK("http://x")']);

        $res = $this->actingAs($psi)->post('/integracoes/sinan/arquivo', ['ids' => [$case->id]])->assertOk();
        $csv = $res->getContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF".implode(';', SinanExporter::COLUMNS), $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
        $d = $case->dispatches()->firstOrFail();
        $this->assertSame(DispatchStatus::Gerado, $d->status);
        $this->assertSame(64, strlen($d->payload_hash));
        $this->assertDatabaseHas('audit_logs', ['user_id' => $psi->id, 'action' => AuditAction::IntegrationGenerated->value, 'case_id' => $case->id]);
    }

    public function test_sinan_file_skips_cases_user_cannot_read(): void
    {
        $a = $this->muni();
        $b = $this->muni(['ibge_code' => '3304557']);
        $psi = $this->user(Role::Psicologo, $a);
        $mine = $this->sinanCase($a, [], ['responsible_id' => $psi->id]);
        $foreign = $this->sinanCase($b, [], ['responsible_id' => $this->user(Role::Psicologo, $b)->id]);

        $csv = $this->actingAs($psi)->post('/integracoes/sinan/arquivo', ['ids' => [$mine->id, $foreign->id]])->assertOk()->getContent();
        $this->assertSame(2, substr_count(trim($csv), "\n") + 1); // cabeçalho + 1 linha
        $this->assertSame(0, $foreign->dispatches()->count());

        $this->actingAs($psi)->post('/integracoes/sinan/arquivo', ['ids' => [$foreign->id]])->assertForbidden();
        $this->actingAs($this->user(Role::Recepcao, $a))->post('/integracoes/sinan/arquivo', ['ids' => [$mine->id]])->assertForbidden();
    }

    public function test_sinan_confirmation_waiver_and_pending_list(): void
    {
        $m = $this->muni();
        $coord = $this->user(Role::Coordenacao, $m);
        $a = $this->sinanCase($m);
        $b = $this->sinanCase($m);
        $c = $this->sinanCase($m);

        $this->actingAs($coord)->get('/integracoes')->assertOk()->assertSee($a->protocol)->assertSee($b->protocol)->assertSee($c->protocol);

        $this->post('/integracoes/sinan/arquivo', ['ids' => [$a->id]])->assertOk();
        $d = $a->dispatches()->first();
        $this->post("/integracoes/{$d->id}/concluir", ['reference' => 'ABC', 'sent_at' => now()->toDateString()])->assertSessionHasErrors('reference');
        $this->post("/integracoes/{$d->id}/concluir", ['reference' => '1234567', 'sent_at' => now()->toDateString()])->assertSessionHasNoErrors();
        $this->post("/integracoes/{$d->id}/concluir", ['reference' => '1234567', 'sent_at' => now()->toDateString()])->assertStatus(422); // não repete

        $this->post("/casos/{$b->id}/sinan/dispensar", ['reason' => 'curta'])->assertSessionHasErrors('reason');
        $this->post("/casos/{$b->id}/sinan/dispensar", ['reason' => 'Notificado pela UBS Central, nº 7654321'])->assertSessionHasNoErrors();

        $this->get('/integracoes')->assertSee($c->protocol)->assertDontSee($a->protocol)->assertDontSee($b->protocol);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::IntegrationWaived->value, 'case_id' => $b->id]);

        $this->actingAs($this->user(Role::Psicologo, $m))->get('/integracoes')->assertForbidden();
    }

    public function test_witness_cases_do_not_require_sinan_and_anonymized_cases_cannot_integrate(): void
    {
        $m = $this->muni();
        $coord = $this->user(Role::Coordenacao, $m);
        $witness = $this->sinanCase($m, [], ['condition' => 'testemunha']);
        $this->assertFalse(SinanExporter::requires($witness));
        $this->actingAs($coord)->get('/integracoes')->assertDontSee($witness->protocol);

        $done = $this->makeCase($m);
        $this->artisan('sigiep:retencao');
        $this->assertTrue($done->fresh()->isAnonymized());
        $this->post("/casos/{$done->id}/processos", ['number' => self::CNJ])->assertForbidden();
        $this->post("/casos/{$done->id}/sinan/dispensar", ['reason' => 'Motivo qualquer válido'])->assertForbidden();
        $this->post("/casos/{$done->id}/justica/comunicacao", ['summary' => str_repeat('x', 30)])->assertForbidden();
        $this->assertNotEmpty((new SinanExporter)->map($done->fresh())['errors']);
    }

    public function test_sinan_deadline_depends_on_violence_type_and_alerts_mention_overdue(): void
    {
        Notification::fake();
        $m = $this->muni();
        $coord = $this->user(Role::Coordenacao, $m);
        $sexual = $this->sinanCase($m, [], ['received_at' => now()->subDays(3), 'violence_types' => ['sexual']]);
        $fisica = $this->sinanCase($m, [], ['received_at' => now()->subDays(3), 'violence_types' => ['fisica']]);

        $this->assertSame(now()->subDays(2)->toDateString(), SinanExporter::deadline($sexual)->toDateString());
        $this->assertSame(now()->addDays(4)->toDateString(), SinanExporter::deadline($fisica)->toDateString());

        $this->artisan('sigiep:alertas')->assertSuccessful();
        Notification::assertSentTo($coord, AlertasDiarios::class,
            fn ($n) => $n->dados['sinan'] === [$sexual->protocol]);
    }

    public function test_statistics_age_bands_use_whole_years(): void
    {
        $m = $this->muni();
        $coord = $this->user(Role::Coordenacao, $m);
        // 5 anos e 6 meses completos: faixa 0–5 (não 6–11).
        $this->makeCase($m, ['received_at' => now()], Child::create(['name' => 'X', 'birth_date' => now()->subYears(5)->subMonths(6)]));

        $data = app(StatsController::class)->data(
            request()->duplicate(['de' => now()->subMonth()->toDateString()], null)->setUserResolver(fn () => $coord)
        );
        $this->assertSame(1, $data['byAge']['0–5']);
        $this->assertSame(0, $data['byAge']['6–11']);
    }

    // ===================== IBGE, ficha SINAN e prontidão =====================

    public function test_ibge_code_validation(): void
    {
        $this->assertTrue(Ibge::isValid('3550308')); // São Paulo/SP
        $this->assertTrue(Ibge::isValid('3304557')); // Rio de Janeiro/RJ
        $this->assertFalse(Ibge::isValid('3550309')); // DV errado
        $this->assertFalse(Ibge::isValid('9950308')); // UF inexistente
        $this->assertFalse(Ibge::isValid('355030'));
        $this->assertSame('SP', Ibge::uf('3550308'));
    }

    public function test_municipality_form_rejects_wrong_ibge_or_uf_mismatch(): void
    {
        $admin = User::factory()->role(Role::Administrador)->create(['municipality_id' => null]);
        $base = ['name' => 'Cidade', 'uf' => 'SP', 'retention_years' => 10, 'sinan_unit_type' => 2, 'active' => 1];
        $this->actingAs($admin);

        $this->post('/admin/municipios', [...$base, 'ibge_code' => '3550309'])->assertSessionHasErrors('ibge_code');
        $this->post('/admin/municipios', [...$base, 'uf' => 'RJ', 'ibge_code' => '3550308'])->assertSessionHasErrors('ibge_code'); // código de SP em município do RJ
        $this->post('/admin/municipios', [...$base, 'ibge_code' => '3550308'])->assertSessionHasNoErrors();
        $this->assertSame('3550308', Municipality::where('name', 'Cidade')->value('ibge_code'));
    }

    public function test_municipality_readiness_and_dashboard_warning(): void
    {
        $m = Municipality::factory()->create(['ibge_code' => null, 'sinan_unit_name' => null]);
        $coord = $this->user(Role::Coordenacao, $m);

        $this->assertSame([false, false, false], array_values($m->readiness()));
        $this->actingAs($coord)->get('/')->assertSee('Configuração do município pendente')->assertSee('Código IBGE válido');

        $m->update(['ibge_code' => '3550308', 'sinan_unit_name' => 'CREAS']);
        $m->flowSteps()->create(['name' => 'CT', 'destination' => 'conselho_tutelar', 'due_days' => 1]);
        $this->assertSame([true, true, true], array_values($m->fresh()->readiness()));
        $this->actingAs($coord->fresh())->get('/')->assertDontSee('Configuração do município pendente'); // requisição nova recarrega o município
    }

    public function test_sinan_sheet_pdf_lists_official_field_numbers_and_registers_dispatch(): void
    {
        $m = $this->muni();
        $psi = $this->user(Role::Psicologo, $m);
        $case = $this->sinanCase($m, [], ['responsible_id' => $psi->id]);

        $items = (new SinanExporter)->sheet((new SinanExporter)->map($case)['row']);
        $byN = collect($items)->keyBy('n');
        $this->assertSame(['1', '2', '3', '4', '5', '6', '7', '9', '10', '11', '12', '13', '15', '18', '19', '20', '22', '23', '40', '41', '50', '52', '53', '56', '57', '58', '60', '61', '62', '63', '64', '65'],
            collect($items)->pluck('n')->all());
        $this->assertStringContainsString('Residência (01)', $byN['52']['value']);
        $this->assertSame('Sim (1)', $byN['58']['group']['Estupro']);
        $this->assertSame('Não (2)', $byN['58']['group']['Assédio sexual']);
        $this->assertSame('Sim (1)', $byN['61']['group']['Padrasto']);
        $this->assertStringContainsString('Pessoa adulta (4)', $byN['64']['value']);

        $res = $this->actingAs($psi)->post("/casos/{$case->id}/sinan/ficha")->assertOk();
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertSame(1, $case->dispatches()->where('system', 'sinan')->where('status', 'gerado')->count());
        $this->assertDatabaseHas('audit_logs', ['user_id' => $psi->id, 'action' => AuditAction::IntegrationGenerated->value]);

        $this->actingAs($this->user(Role::Recepcao, $m))->post("/casos/{$case->id}/sinan/ficha")->assertForbidden();
    }

    public function test_sinan_sheet_is_blocked_with_missing_mandatory_data(): void
    {
        $m = $this->muni(['sinan_unit_name' => null]);
        $psi = $this->user(Role::Psicologo, $m);
        $case = $this->sinanCase($m, [], ['responsible_id' => $psi->id]);

        $this->actingAs($psi)->post("/casos/{$case->id}/sinan/ficha")->assertSessionHasErrors();
        $this->assertSame(0, $case->dispatches()->count());
    }

    public function test_sinan_relationship_categories_follow_the_official_list(): void
    {
        $m = $this->muni();
        foreach (['namorado' => 'REL_NAMO', 'ex_conjuge' => 'REL_EXCON', 'filho' => 'REL_FILHO', 'policial' => 'REL_POL',
            'patrao_chefe' => 'REL_PATRAO', 'proprietario_estabelecimento' => 'REL_PROPRI', 'outro_familiar' => 'REL_OUTROS'] as $opt => $col) {
            $row = (new SinanExporter)->map($this->sinanCase($m, ['vinculo_autor' => $opt]))['row'];
            $this->assertSame('1', $row[$col], "{$opt} → {$col}");
            $this->assertSame('2', $row['REL_PAI']);
        }
        $row = (new SinanExporter)->map($this->sinanCase($m, ['faixa_autor' => 'adolescente']))['row'];
        $this->assertSame('2', $row['CICL_VID_AU']);
    }
}
