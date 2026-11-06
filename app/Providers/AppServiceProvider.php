<?php

namespace App\Providers;

use App\Models\Municipality;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Login: 6 tentativas/min por e-mail+IP (quem erra a senha não trava os colegas)
        // e teto de 30/min por IP contra varredura de e-mails.
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(6)->by(mb_strtolower((string) $r->input('email')).'|'.$r->ip()),
            Limit::perMinute(30)->by($r->ip()),
        ]);

        ResetPassword::toMailUsing(fn ($notifiable, string $token) => (new MailMessage)
            ->subject('SIGIEP · redefinição de senha')
            ->line('Recebemos um pedido para redefinir a senha da sua conta.')
            ->action('Redefinir senha', url(route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], false)))
            ->line('O link vale por 60 minutos. Se não foi você, ignore este e-mail: nada será alterado.'));

        // CaseRecordPolicy é descoberta por convenção (App\Policies\{Model}Policy).
        Gate::define('admin', fn (User $u) => $u->active && $u->isAdmin());
        Gate::define('viewAudit', fn (User $u) => $u->active && ($u->isAdmin() || $u->isCoordination()));
        Gate::define('viewRetention', fn (User $u) => $u->active && ($u->isAdmin() || $u->isCoordination()));
        Gate::define('viewStats', fn (User $u) => $u->active && ($u->isAdmin() || $u->isCoordination()));
        // Fluxo do município: administrador (qualquer) ou coordenação do próprio município.
        Gate::define('manageFlow', fn (User $u, Municipality $m) => $u->active
            && ($u->isAdmin() || ($u->isCoordination() && $u->municipality_id === $m->id)));
    }
}
