<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user?->must_change_password && ! $request->routeIs('senha.*', 'logout')) {
            return redirect()->route('senha.edit')->with('ok', 'Defina uma nova senha para continuar.');
        }

        return $next($request);
    }
}
