<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt([...$credentials, 'active' => true], $request->boolean('remember'))) {
            $request->session()->regenerate();
            // Todo login recomeça sem 2FA verificado; o desafio é por sessão.
            $request->session()->forget('2fa_passed');
            AuditLog::record(AuditAction::Login);

            return redirect()->intended(route('dashboard'));
        }

        $target = User::where('email', $credentials['email'])->first();
        AuditLog::create([
            'user_id' => $target?->id,
            'user_name' => $credentials['email'],
            'action' => AuditAction::LoginFailed,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);

        return back()->withErrors(['email' => 'Credenciais inválidas ou usuário inativo.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        AuditLog::record(AuditAction::Logout);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
