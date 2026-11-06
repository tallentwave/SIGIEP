<?php

namespace Tests\Feature;

use App\Enums\CaseStatus;
use App\Enums\Role;
use App\Models\CaseRecord;
use App\Models\Child;
use App\Models\Municipality;
use App\Models\User;
use App\Notifications\AlertasDiarios;
use App\Services\SinanExporter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EndurecimentoPrazosTest extends TestCase
{
    use RefreshDatabase;

    private function muni(array $over = []): Municipality
    {
        return Municipality::factory()->create($over);
    }

    private function user(Role $role, ?Municipality $m = null, array $attrs = []): User
    {
        $f = User::factory()->role($role);

        return ($m ? $f->inMunicipality($m) : $f)->create($attrs);
    }

    private function makeCase(Municipality $m, array $attrs = [], ?Child $child = null): CaseRecord
    {
        $child ??= Child::create(['name' => 'Criança '.uniqid()]);

        return CaseRecord::createWithProtocol([
            'municipality_id' => $m->id, 'child_id' => $child->id, 'condition' => 'vitima', 'violence_types' => ['fisica'],
            'origin' => 'CT', 'received_at' => now(), 'status' => CaseStatus::Recebido, ...$attrs,
        ]);
    }

    // ===================== cabeçalhos =====================

    public function test_every_response_has_security_headers_and_is_not_cacheable(): void
    {
        $res = $this->get('/login');
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
        $this->assertSame('DENY', $res->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $res->headers->get('X-Content-Type-Options'));
        $this->assertSame('no-referrer', $res->headers->get('Referrer-Policy'));
        $csp = $res->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertNull($res->headers->get('Strict-Transport-Security')); // só em HTTPS

        $this->withServerVariables(['HTTPS' => 'on'])->get('https://localhost/login')
            ->assertHeader('Strict-Transport-Security');
    }

    public function test_authenticated_pages_and_downloads_are_never_cached(): void
    {
        $m = $this->muni(['ibge_code' => '3550308', 'sinan_unit_name' => 'X']);
        $coord = $this->user(Role::Coordenacao, $m);
        $case = $this->makeCase($m);

        $this->actingAs($coord);
        $this->assertStringContainsString('no-store', $this->get("/casos/{$case->id}")->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $this->get('/estatisticas/pdf')->headers->get('Cache-Control'));
    }

    public function test_no_view_depends_on_javascript(): void
    {
        // A CSP bloqueia scripts; qualquer <script> ou handler inline (onclick=…) simplesmente não funcionaria.
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('resources/views'), \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (str_ends_with($f->getFilename(), '.blade.php')) {
                $files[] = $f->getPathname();
            }
        }
        $this->assertGreaterThan(30, count($files), 'a varredura deveria cobrir todas as views');

        foreach ($files as $f) {
            $this->assertDoesNotMatchRegularExpression('/<script|\son(click|submit|load|change|error|input)\s*=/i', file_get_contents($f), $f);
        }
    }

    public function test_dangerous_skeleton_workflows_were_removed(): void
    {
        foreach (['dependabot-auto-merge', 'issues', 'pull-requests', 'update-changelog'] as $w) {
            $this->assertFileDoesNotExist(base_path(".github/workflows/{$w}.yml"));
        }
        $this->assertFileExists(base_path('.github/workflows/tests.yml'));
    }

    // ===================== login =====================

    public function test_login_limit_is_per_email_so_one_user_cannot_lock_out_colleagues(): void
    {
        $victim = User::factory()->create(['email' => 'vitima@x.com', 'password' => 'SenhaForte123']);
        User::factory()->create(['email' => 'colega@x.com', 'password' => 'SenhaForte123']);

        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => 'vitima@x.com', 'password' => 'errada'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'vitima@x.com', 'password' => 'errada'])->assertStatus(429);
        $this->post('/login', ['email' => 'VITIMA@x.com', 'password' => 'SenhaForte123'])->assertStatus(429); // maiúsculas não burlam

        // O colega, no mesmo IP, continua entrando.
        $this->post('/login', ['email' => 'colega@x.com', 'password' => 'SenhaForte123'])->assertRedirect('/');
        $this->assertNotNull($victim);
    }

    // ===================== corridas =====================

    /**
     * Modelo cuja 1ª tentativa de protocolo colide com um caso que "outro usuário acabou de gravar".
     * Subclasse (e não listener estático) para não vazar estado entre testes.
     */
    private function racingModel(int $collisions): string
    {
        $cls = new class extends CaseRecord
        {
            protected $table = 'cases';

            public static int $left = 0;

            public static string $stale = '';

            public static function nextProtocol(): string
            {
                return self::$left-- > 0 ? self::$stale : parent::nextProtocol();
            }
        };
        $cls::$left = $collisions;
        $cls::$stale = 'SIG-'.now()->year.'-00001';

        return $cls::class;
    }

    public function test_protocol_collision_is_retried_instead_of_failing(): void
    {
        $m = $this->muni();
        $this->makeCase($m); // outro cadastro já gravou o protocolo 00001
        $model = $this->racingModel(1); // nosso processo ainda achava que 00001 estava livre

        $case = $model::createWithProtocol([
            'municipality_id' => $m->id, 'child_id' => Child::create(['name' => 'X'])->id, 'condition' => 'vitima',
            'violence_types' => ['fisica'], 'origin' => 'CT', 'received_at' => now(), 'status' => CaseStatus::Recebido,
        ]);

        $this->assertSame('SIG-'.now()->year.'-00002', $case->protocol);
        $this->assertSame(2, CaseRecord::count());
    }

    public function test_protocol_gives_up_after_max_tries(): void
    {
        $m = $this->muni();
        $this->makeCase($m);
        $model = $this->racingModel(99);

        $this->expectException(UniqueConstraintViolationException::class);
        $model::createWithProtocol([
            'municipality_id' => $m->id, 'child_id' => Child::create(['name' => 'X'])->id, 'condition' => 'vitima',
            'violence_types' => ['fisica'], 'origin' => 'CT', 'received_at' => now(), 'status' => CaseStatus::Recebido,
        ], 3);
    }

    // ===================== CPF =====================

    public function test_cpf_is_encrypted_at_rest_and_searchable_only_by_exact_number(): void
    {
        $m = $this->muni();
        $psi = $this->user(Role::Psicologo, $m);
        $child = Child::create(['name' => 'Joana Cpf', 'cpf' => '123.456.789-09']);
        $child->guardians()->create(['name' => 'Mãe', 'cpf' => '987.654.321-00']);
        $this->makeCase($m, ['responsible_id' => $psi->id], $child);

        $raw = DB::table('children')->first();
        $this->assertStringNotContainsString('123', $raw->cpf);
        $this->assertSame(64, strlen($raw->cpf_hash));
        $this->assertStringNotContainsString('987', (string) DB::table('guardians')->value('cpf'));
        $this->assertSame('123.456.789-09', $child->fresh()->cpf);

        $this->actingAs($psi);
        $this->get('/atendidos?q=12345678909')->assertSee('Joana Cpf');      // sem pontuação
        $this->get('/atendidos?q=123.456.789-09')->assertSee('Joana Cpf');   // com pontuação
        $this->get('/atendidos?q=123456')->assertDontSee('Joana Cpf');       // parcial não vaza

        $child->update(['cpf' => null]);
        $this->assertNull(DB::table('children')->value('cpf_hash'));
    }

    // ===================== verificação de produção =====================

    public function test_verificar_fails_strict_with_unsafe_settings_and_names_the_problem(): void
    {
        config(['app.debug' => true, 'mail.default' => 'log', 'session.secure' => false]);

        $this->artisan('sigiep:verificar --strict')
            ->expectsOutputToContain('APP_DEBUG desligado')
            ->expectsOutputToContain('E-mail real configurado')
            ->assertFailed();
    }

    public function test_verificar_passes_with_production_grade_settings_and_reports_users_without_2fa(): void
    {
        config(['app.debug' => false, 'app.url' => 'https://sigiep.exemplo.gov.br', 'session.secure' => true, 'session.lifetime' => 30,
            'mail.default' => 'smtp', 'database.connections.sqlite.driver' => 'mysql', // só a leitura do driver; a conexão ativa não muda
            'sigiep.two_factor_required_roles' => ['administrador', 'coordenacao', 'psicologo', 'assistente_social']]);

        $this->artisan('sigiep:verificar --strict')->expectsOutputToContain('Configuração crítica OK')->assertSuccessful();
    }

    public function test_verificar_flags_when_2fa_requirement_was_weakened(): void
    {
        config(['sigiep.two_factor_required_roles' => []]);
        $this->artisan('sigiep:verificar --strict')->expectsOutputToContain('2FA obrigatório')->assertFailed();
    }

    // ===================== prazos por município =====================

    public function test_coordination_edits_own_deadlines_only_and_validates_them(): void
    {
        $a = $this->muni();
        $b = $this->muni();
        $coord = $this->user(Role::Coordenacao, $a);
        $body = ['sinan_deadline_sexual_days' => 0, 'sinan_deadline_default_days' => 5, 'queue_alert_days' => 2, 'first_listening_days' => 4];

        $this->actingAs($coord)->get("/admin/municipios/{$a->id}/prazos")->assertOk()->assertSee('notificação imediata');
        $this->get("/admin/municipios/{$b->id}/prazos")->assertForbidden();
        $this->put("/admin/municipios/{$b->id}/prazos", $body)->assertForbidden();

        $this->put("/admin/municipios/{$a->id}/prazos", [...$body, 'sinan_deadline_sexual_days' => 9])->assertSessionHasErrors('sinan_deadline_sexual_days'); // sexual > demais
        $this->put("/admin/municipios/{$a->id}/prazos", [...$body, 'queue_alert_days' => 0])->assertSessionHasErrors('queue_alert_days');
        $this->put("/admin/municipios/{$a->id}/prazos", [...$body, 'sinan_deadline_default_days' => 'abc'])->assertSessionHasErrors('sinan_deadline_default_days');

        $this->put("/admin/municipios/{$a->id}/prazos", $body)->assertSessionHasNoErrors();
        $a->refresh();
        $this->assertSame([0, 5, 2, 4], [$a->sinan_deadline_sexual_days, $a->sinan_deadline_default_days, $a->queue_alert_days, $a->first_listening_days]);
        $this->assertSame([1, 7, 3], [$b->fresh()->sinan_deadline_sexual_days, $b->fresh()->sinan_deadline_default_days, $b->fresh()->queue_alert_days]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $coord->id, 'action' => 'alterou', 'subject_type' => (new Municipality)->getMorphClass(), 'subject_id' => $a->id]);
    }

    public function test_other_profiles_cannot_change_deadlines_but_admin_can(): void
    {
        $m = $this->muni();
        $body = ['sinan_deadline_sexual_days' => 1, 'sinan_deadline_default_days' => 3, 'queue_alert_days' => 1, 'first_listening_days' => 4];

        foreach ([Role::Psicologo, Role::AssistenteSocial, Role::Recepcao] as $role) {
            $this->actingAs($this->user($role, $m))->put("/admin/municipios/{$m->id}/prazos", $body)->assertForbidden();
        }
        $admin = $this->user(Role::Administrador, null, ['municipality_id' => null]);
        $this->actingAs($admin)->put("/admin/municipios/{$m->id}/prazos", $body)->assertSessionHasNoErrors();
        $this->assertSame(3, $m->fresh()->sinan_deadline_default_days);
    }

    public function test_deadline_follows_the_municipality_settings_for_existing_cases(): void
    {
        $m = $this->muni();
        $sexual = $this->makeCase($m, ['received_at' => now()->subDays(2), 'violence_types' => ['sexual']]);
        $fisica = $this->makeCase($m, ['received_at' => now()->subDays(2), 'violence_types' => ['fisica']]);

        $this->assertSame(now()->subDay()->toDateString(), SinanExporter::deadline($sexual)->toDateString()); // 1 dia
        $this->assertSame(now()->addDays(5)->toDateString(), SinanExporter::deadline($fisica)->toDateString()); // 7 dias

        $m->update(['sinan_deadline_sexual_days' => 0, 'sinan_deadline_default_days' => 2]);

        $this->assertSame(now()->subDays(2)->toDateString(), SinanExporter::deadline($sexual->fresh())->toDateString());
        $this->assertSame(now()->toDateString(), SinanExporter::deadline($fisica->fresh())->toDateString());
    }

    public function test_queue_alert_days_is_per_municipality(): void
    {
        Notification::fake();
        $quick = $this->muni(['queue_alert_days' => 1]);
        $slow = $this->muni(['queue_alert_days' => 10]);
        $coordQuick = $this->user(Role::Coordenacao, $quick);
        $coordSlow = $this->user(Role::Coordenacao, $slow);
        $a = $this->makeCase($quick, ['received_at' => now()->subDays(2)]);
        $this->makeCase($slow, ['received_at' => now()->subDays(2)]);

        $this->artisan('sigiep:alertas')->assertSuccessful();

        Notification::assertSentTo($coordQuick, AlertasDiarios::class, fn ($n) => $n->dados['fila'] === [$a->protocol]);
        Notification::assertNotSentTo($coordSlow, AlertasDiarios::class);
    }

    // ===================== config:cache e proxies =====================

    public function test_env_is_never_called_outside_config_files(): void
    {
        // Com `config:cache`, env() fora de config/ devolve NULL e a configuração some em silêncio.
        $offenders = [];
        foreach (['app', 'bootstrap', 'routes', 'database'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->getExtension() === 'php' && ! str_contains($f->getPathname(), '/bootstrap/cache/')
                    && preg_match('/\benv\(/', file_get_contents($f->getPathname()))) {
                    $offenders[] = str_replace(base_path().'/', '', $f->getPathname());
                }
            }
        }
        $this->assertSame([], $offenders, 'env() só pode ser usado em config/');
    }

    public function test_forwarded_ip_is_used_only_from_trusted_proxies(): void
    {
        User::factory()->create(['email' => 'a@x.com']);
        $post = fn () => $this->withServerVariables(['REMOTE_ADDR' => '10.1.1.1'])->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
            ->post('/login', ['email' => 'a@x.com', 'password' => 'errada']);

        $post();
        $this->assertDatabaseHas('audit_logs', ['action' => 'login_falhou', 'ip' => '10.1.1.1']); // sem confiar: IP do proxy

        config(['trustedproxy.proxies' => '10.0.0.0/8']);
        $post();
        $this->assertDatabaseHas('audit_logs', ['action' => 'login_falhou', 'ip' => '203.0.113.9']); // proxy confiável: IP real
    }

    // ===================== primeiro administrador =====================

    public function test_create_admin_command_creates_a_forced_password_change_account(): void
    {
        $this->artisan('sigiep:criar-admin Admin@Orgao.gov.br --nome="Fulana de Tal"')
            ->expectsQuestion('Senha (não aparece na tela)', 'SenhaInicial123')
            ->expectsQuestion('Repita a senha', 'SenhaInicial123')
            ->expectsOutputToContain('criado')
            ->assertSuccessful();

        $u = User::where('email', 'admin@orgao.gov.br')->firstOrFail();
        $this->assertSame(Role::Administrador, $u->role);
        $this->assertNull($u->municipality_id);
        $this->assertTrue($u->must_change_password && $u->active);
        $this->assertTrue(Hash::check('SenhaInicial123', $u->password));
        config(['sigiep.two_factor_required_roles' => ['administrador']]); // a base de testes desliga o 2FA
        $this->assertTrue($u->requiresTwoFactor());
    }

    public function test_create_admin_command_rejects_weak_mismatched_and_duplicate(): void
    {
        $this->artisan('sigiep:criar-admin a@b.com --nome=X')->expectsQuestion('Senha (não aparece na tela)', 'curta')
            ->expectsQuestion('Repita a senha', 'curta')->expectsOutputToContain('Senha fraca')->assertFailed();
        $this->artisan('sigiep:criar-admin a@b.com --nome=X')->expectsQuestion('Senha (não aparece na tela)', 'SenhaInicial123')
            ->expectsQuestion('Repita a senha', 'OutraSenha456')->expectsOutputToContain('não conferem')->assertFailed();
        $this->assertSame(0, User::where('email', 'a@b.com')->count());

        User::factory()->create(['email' => 'dup@b.com']);
        $this->artisan('sigiep:criar-admin dup@b.com --nome=X')->expectsOutputToContain('Já existe')->assertFailed();
        $this->artisan('sigiep:criar-admin nao-e-email --nome=X')->assertFailed();
    }

    public function test_verificar_warns_about_placeholder_mail_sender_without_failing(): void
    {
        config(['mail.from.address' => 'hello@example.com', 'app.debug' => false, 'app.url' => 'https://x.gov.br', 'session.secure' => true,
            'session.lifetime' => 30, 'mail.default' => 'smtp', 'database.connections.sqlite.driver' => 'mysql',
            'sigiep.two_factor_required_roles' => ['administrador', 'coordenacao', 'psicologo', 'assistente_social']]);

        $this->artisan('sigiep:verificar --strict')->expectsOutputToContain('Remetente de e-mail próprio')->assertSuccessful(); // aviso, não crítico
    }

    public function test_timezone_can_be_configured_per_deployment(): void
    {
        $this->assertSame('America/Sao_Paulo', config('app.timezone'));
        $this->assertStringContainsString("env('APP_TIMEZONE'", file_get_contents(base_path('config/app.php')));
    }
}
