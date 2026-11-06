<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Quem tem 2FA precisa passar pelo desafio a cada sessão; quem é obrigado a
 * ter e ainda não configurou é levado à configuração antes de qualquer tela.
 */
class EnsureTwoFactor
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || $request->routeIs('2fa.setup', '2fa.confirm', '2fa.challenge', '2fa.verify', 'logout')) {
            return $next($request);
        }

        if ($user->hasTwoFactor()) {
            if (! $request->session()->get('2fa_passed')) {
                return redirect()->route('2fa.challenge');
            }
        } elseif ($user->requiresTwoFactor()) {
            return redirect()->route('2fa.setup')
                ->with('ok', 'Seu perfil exige verificação em duas etapas. Configure para continuar.');
        }

        return $next($request);
    }
}
