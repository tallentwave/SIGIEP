<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('sigiep:verificar {--strict : Falha (exit 1) se algo crítico estiver fora do padrão, mesmo fora de produção}')]
#[Description('Confere a configuração de segurança para produção (debug, HTTPS, e-mail, sessão, 2FA, proxies)')]
class VerificarProducao extends Command
{
    public function handle(): int
    {
        $isProd = app()->isProduction();
        $critical = fn (bool $ok, string $label, string $fix) => ['crit', $ok, $label, $fix];
        $warn = fn (bool $ok, string $label, string $fix) => ['warn', $ok, $label, $fix];

        $checks = [
            $critical(! config('app.debug'), 'APP_DEBUG desligado', 'Defina APP_DEBUG=false: o modo debug mostra variáveis e dados nas telas de erro.'),
            $critical(filled(config('app.key')), 'APP_KEY definida', 'php artisan key:generate (e guarde um backup: sem ela os relatos criptografados são irrecuperáveis).'),
            $critical(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL usa HTTPS', 'Defina APP_URL=https://… e sirva o sistema só por HTTPS.'),
            $critical((bool) config('session.secure'), 'Cookie de sessão só por HTTPS', 'Defina SESSION_SECURE_COOKIE=true.'),
            $critical((int) config('session.lifetime') <= 60, 'Sessão ociosa expira em até 60 min', 'Defina SESSION_LIFETIME=30.'),
            $critical(! in_array(config('mail.default'), ['log', 'array'], true), 'E-mail real configurado', 'Com MAIL_MAILER=log, links de redefinição de senha e alertas vão para o arquivo de log. Configure MAIL_*.'),
            $critical(! array_diff(array_map(fn (Role $r) => $r->value, [Role::Administrador, Role::Coordenacao, Role::Psicologo, Role::AssistenteSocial]),
                config('sigiep.two_factor_required_roles', [])), '2FA obrigatório para perfis sensíveis', 'Remova SIGIEP_2FA_REQUIRED do ambiente (só para desenvolvimento).'),
            $critical(config('database.connections.'.config('database.default').'.driver') !== 'sqlite', 'Banco de dados de produção (não SQLite)', 'Use MySQL ou PostgreSQL com backup e disco criptografado.'),
            $warn(! str_ends_with((string) config('mail.from.address'), '@example.com'), 'Remetente de e-mail próprio (MAIL_FROM_ADDRESS)', 'Defina MAIL_FROM_ADDRESS com um endereço do órgão; o padrão (hello@example.com) é recusado por muitos provedores.'),
            $warn(filled(config('trustedproxy.proxies')) || ! request()->hasHeader('X-Forwarded-For'), 'Proxies confiáveis (TRUSTED_PROXIES)', 'Atrás de balanceador, defina TRUSTED_PROXIES; senão a auditoria grava o IP do proxy e o limite de login é compartilhado.'),
            $warn((int) config('sigiep.audit_retention_years') > 0, 'Prazo de expurgo da auditoria definido', 'Defina SIGIEP_AUDIT_RETENTION_YEARS (padrão 5).'),
            $warn(config('app.env') === 'production', 'APP_ENV=production', 'Defina APP_ENV=production.'),
        ];

        $failed = 0;
        $warned = 0;
        foreach ($checks as [$level, $ok, $label, $fix]) {
            if ($ok) {
                $this->line("  <info>✓</info> {$label}");

                continue;
            }
            $level === 'crit' ? $failed++ : $warned++;
            $this->line(($level === 'crit' ? '  <error> ✗ </error> ' : '  <comment>!</comment> ')."{$label}\n      → {$fix}");
        }

        // Dado de operação: quem deveria ter 2FA e ainda não tem.
        try {
            $without = User::where('active', true)->whereIn('role', config('sigiep.two_factor_required_roles', []))->whereNull('two_factor_confirmed_at')->count();
            $this->line($without ? "  <comment>!</comment> {$without} usuário(s) de perfil obrigatório ainda sem 2FA (serão forçados no próximo login)." : '  <info>✓</info> Todos os usuários de perfil obrigatório têm 2FA.');
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $this->line('  <comment>!</comment> Não foi possível consultar o banco.');
        }

        $this->newLine();
        $this->line($failed ? "<error> {$failed} item(ns) crítico(s) fora do padrão de produção. </error>" : '<info>Configuração crítica OK.</info>');
        if ($warned) {
            $this->line("{$warned} aviso(s).");
        }

        return ($failed && ($isProd || $this->option('strict'))) ? self::FAILURE : self::SUCCESS;
    }
}
