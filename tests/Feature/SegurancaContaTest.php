<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SegurancaContaTest extends TestCase
{
    use RefreshDatabase;

    private function otp(string $secret): string
    {
        return (new Google2FA)->getCurrentOtp($secret);
    }

    /** Usuário com 2FA já ativo (segredo conhecido pelo teste). */
    private function userWith2fa(Role $role = Role::Psicologo, array $attrs = []): User
    {
        return User::factory()->role($role)->create([
            'email' => 'u'.uniqid().'@x.com', 'password' => 'SenhaForte123',
            'two_factor_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
            'two_factor_recovery_codes' => [hash('sha256', 'abcde-12345')],
            'two_factor_confirmed_at' => now(), ...$attrs,
        ]);
    }

    // ---------- recuperação de senha ----------

    public function test_reset_link_is_sent_only_to_active_accounts_with_identical_response(): void
    {
        Notification::fake();
        $ok = User::factory()->create(['email' => 'ok@x.com']);
        $off = User::factory()->create(['email' => 'off@x.com', 'active' => false]);

        $msg = fn ($r) => $r->assertSessionHas('ok')->getSession()->get('ok');
        $a = $msg($this->post('/esqueci-senha', ['email' => 'ok@x.com']));
        $b = $msg($this->post('/esqueci-senha', ['email' => 'off@x.com']));
        $c = $msg($this->post('/esqueci-senha', ['email' => 'ninguem@x.com']));

        $this->assertSame($a, $b);
        $this->assertSame($a, $c); // não revela quais e-mails existem
        Notification::assertSentTo($ok, ResetPassword::class);
        Notification::assertNotSentTo($off, ResetPassword::class);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $ok->id, 'action' => AuditAction::PasswordResetRequested->value]);
    }

    public function test_reset_flow_changes_password_clears_flag_and_kills_sessions(): void
    {
        $u = User::factory()->create(['email' => 'r@x.com', 'password' => 'Antiga12345', 'must_change_password' => true]);
        DB::table('sessions')->insert(['id' => 's1', 'user_id' => $u->id, 'payload' => '', 'last_activity' => time()]);
        $token = Password::createToken($u);

        $this->get("/redefinir-senha/{$token}?email=r@x.com")->assertOk();
        $this->post('/redefinir-senha', ['token' => $token, 'email' => 'r@x.com', 'password' => 'fraca', 'password_confirmation' => 'fraca'])
            ->assertSessionHasErrors('password');
        $this->post('/redefinir-senha', ['token' => $token, 'email' => 'r@x.com', 'password' => 'Nova1234567', 'password_confirmation' => 'Nova1234567'])
            ->assertRedirect('/login');

        $u->refresh();
        $this->assertFalse($u->must_change_password);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $u->id)->count());
        $this->assertDatabaseHas('audit_logs', ['user_id' => $u->id, 'action' => AuditAction::PasswordReset->value]);

        $this->post('/login', ['email' => 'r@x.com', 'password' => 'Nova1234567'])->assertRedirect('/');
        auth()->logout();
        $this->post('/login', ['email' => 'r@x.com', 'password' => 'Antiga12345'])->assertSessionHasErrors('email');

        // Token é de uso único.
        $this->post('/redefinir-senha', ['token' => $token, 'email' => 'r@x.com', 'password' => 'Outra1234567', 'password_confirmation' => 'Outra1234567'])
            ->assertSessionHasErrors('email');
    }

    public function test_reset_rejects_bad_token_and_inactive_account(): void
    {
        $u = User::factory()->create(['email' => 'i@x.com']);
        $token = Password::createToken($u);
        $u->update(['active' => false]);

        $payload = ['email' => 'i@x.com', 'password' => 'Nova1234567', 'password_confirmation' => 'Nova1234567'];
        $this->post('/redefinir-senha', ['token' => $token, ...$payload])->assertSessionHasErrors('email');
        $this->post('/redefinir-senha', ['token' => 'invalido', ...$payload])->assertSessionHasErrors('email');
    }

    public function test_reset_does_not_bypass_two_factor(): void
    {
        $u = $this->userWith2fa();
        $token = Password::createToken($u);
        $this->post('/redefinir-senha', ['token' => $token, 'email' => $u->email, 'password' => 'Nova1234567', 'password_confirmation' => 'Nova1234567']);

        $this->post('/login', ['email' => $u->email, 'password' => 'Nova1234567']);
        $this->get('/casos')->assertRedirect('/2fa/desafio');
    }

    // ---------- 2FA: obrigatoriedade e configuração ----------

    public function test_required_role_without_2fa_is_forced_to_setup(): void
    {
        config(['sigiep.two_factor_required_roles' => [Role::Psicologo->value]]);
        $psi = User::factory()->role(Role::Psicologo)->create();
        $rec = User::factory()->role(Role::Recepcao)->create();

        $this->actingAs($psi)->get('/casos')->assertRedirect('/2fa/configurar');
        $this->actingAs($psi)->get('/2fa/configurar')->assertOk()->assertSee('<svg', false)->assertSee('Chave manual');
        $this->actingAs($rec)->get('/casos')->assertOk(); // recepção: opcional
    }

    public function test_setup_confirms_with_valid_code_and_shows_recovery_codes_once(): void
    {
        config(['sigiep.two_factor_required_roles' => [Role::Psicologo->value]]);
        $u = User::factory()->role(Role::Psicologo)->create();
        $this->actingAs($u)->get('/2fa/configurar');
        $secret = $u->fresh()->two_factor_secret;

        $this->post('/2fa/configurar', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertFalse($u->fresh()->hasTwoFactor());

        $this->post('/2fa/configurar', ['code' => $this->otp($secret)])->assertRedirect('/2fa');
        $this->assertTrue($u->fresh()->hasTwoFactor());
        $this->assertCount(8, $u->fresh()->two_factor_recovery_codes);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $u->id, 'action' => AuditAction::TwoFactorEnabled->value]);

        $this->get('/2fa')->assertOk()->assertSee('não será mostrado novamente');
        $this->get('/2fa')->assertDontSee('não será mostrado novamente'); // flash: só uma vez
        $this->get('/casos')->assertOk(); // sessão atual já está verificada

        $this->assertStringNotContainsString($secret, DB::table('users')->where('id', $u->id)->value('two_factor_secret'));
    }

    // ---------- 2FA: desafio no login ----------

    public function test_login_requires_challenge_and_wrong_code_is_audited(): void
    {
        $u = $this->userWith2fa();

        $this->post('/login', ['email' => $u->email, 'password' => 'SenhaForte123'])->assertRedirect();
        $this->get('/casos')->assertRedirect('/2fa/desafio');
        $this->get('/')->assertRedirect('/2fa/desafio');

        $this->post('/2fa/desafio', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->get('/casos')->assertRedirect('/2fa/desafio');
        $this->assertDatabaseHas('audit_logs', ['user_id' => $u->id, 'action' => AuditAction::TwoFactorFailed->value]);

        $this->post('/2fa/desafio', ['code' => $this->otp('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP')])->assertRedirect();
        $this->get('/casos')->assertOk();
    }

    public function test_totp_code_cannot_be_replayed(): void
    {
        $u = $this->userWith2fa();
        $code = $this->otp($u->two_factor_secret);

        $this->actingAs($u)->post('/2fa/desafio', ['code' => $code])->assertSessionHasNoErrors();
        auth()->logout();
        $this->flushSession();

        // Mesmo código, nova sessão (alguém que "viu por cima do ombro"): recusado.
        $this->post('/login', ['email' => $u->email, 'password' => 'SenhaForte123']);
        $this->post('/2fa/desafio', ['code' => $code])->assertSessionHasErrors('code');
        $this->get('/casos')->assertRedirect('/2fa/desafio');
    }

    public function test_recovery_code_works_once(): void
    {
        $u = $this->userWith2fa();

        $this->actingAs($u)->post('/2fa/desafio', ['recovery_code' => 'ABCDE-12345'])->assertSessionHasNoErrors();
        $this->get('/casos')->assertOk();
        $this->assertCount(0, $u->fresh()->two_factor_recovery_codes);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $u->id, 'action' => AuditAction::TwoFactorRecoveryUsed->value]);

        $this->flushSession();
        $this->post('/2fa/desafio', ['recovery_code' => 'abcde-12345'])->assertSessionHasErrors('code');
    }

    public function test_password_alone_cannot_reach_2fa_management(): void
    {
        $u = $this->userWith2fa(Role::Recepcao);
        $this->actingAs($u); // autenticado, mas SEM passar pelo desafio

        $this->get('/2fa')->assertRedirect('/2fa/desafio');
        $this->delete('/2fa', ['password' => 'SenhaForte123'])->assertRedirect('/2fa/desafio');
        $this->post('/2fa/codigos', ['password' => 'SenhaForte123'])->assertRedirect('/2fa/desafio');
        $this->assertTrue($u->fresh()->hasTwoFactor());
        $this->get('/senha')->assertRedirect('/2fa/desafio'); // nem trocar a senha
    }

    // ---------- 2FA: gerenciamento ----------

    public function test_required_role_cannot_disable_but_optional_role_can(): void
    {
        config(['sigiep.two_factor_required_roles' => [Role::Psicologo->value]]);
        $psi = $this->userWith2fa(Role::Psicologo);
        $rec = $this->userWith2fa(Role::Recepcao);

        $this->actingAs($psi)->withSession(['2fa_passed' => true])->delete('/2fa', ['password' => 'SenhaForte123'])->assertForbidden();
        $this->assertTrue($psi->fresh()->hasTwoFactor());

        $this->actingAs($rec)->withSession(['2fa_passed' => true])->delete('/2fa', ['password' => 'errada'])->assertSessionHasErrors('password');
        $this->delete('/2fa', ['password' => 'SenhaForte123'])->assertRedirect('/2fa');
        $this->assertFalse($rec->fresh()->hasTwoFactor());
        $this->assertDatabaseHas('audit_logs', ['user_id' => $rec->id, 'action' => AuditAction::TwoFactorDisabled->value]);
    }

    public function test_regenerating_recovery_codes_invalidates_old_ones(): void
    {
        $u = $this->userWith2fa();
        $this->actingAs($u)->withSession(['2fa_passed' => true])->post('/2fa/codigos', ['password' => 'SenhaForte123'])->assertRedirect('/2fa');

        $codes = $u->fresh()->two_factor_recovery_codes;
        $this->assertCount(8, $codes);
        $this->assertNotContains(hash('sha256', 'abcde-12345'), $codes);
    }

    public function test_admin_can_reset_lost_2fa_and_it_is_audited(): void
    {
        $admin = User::factory()->role(Role::Administrador)->create(['municipality_id' => null]);
        $target = $this->userWith2fa();

        $this->actingAs($admin)->put("/admin/usuarios/{$target->id}", [
            'name' => $target->name, 'email' => $target->email, 'role' => 'psicologo',
            'municipality_id' => $target->municipality_id, 'active' => 1, 'reset_two_factor' => 1,
        ])->assertSessionHasNoErrors();

        $fresh = $target->fresh();
        $this->assertFalse($fresh->hasTwoFactor());
        $this->assertNull($fresh->two_factor_secret);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => AuditAction::TwoFactorAdminReset->value]);
    }

    public function test_default_config_requires_2fa_for_technical_coordination_and_admin(): void
    {
        $cfg = require base_path('config/sigiep.php');
        $this->assertEqualsCanonicalizing(
            ['administrador', 'coordenacao', 'psicologo', 'assistente_social'],
            $cfg['two_factor_required_roles'],
        );
    }
}
