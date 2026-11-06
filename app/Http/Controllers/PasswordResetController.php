<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/** Recuperação de senha por e-mail. Só contas ativas; resposta idêntica exista o e-mail ou não. */
class PasswordResetController extends Controller
{
    public function request()
    {
        return view('auth.forgot');
    }

    public function email(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']], [], ['email' => 'e-mail']);

        Password::sendResetLink(['email' => $data['email'], 'active' => true]);

        if ($user = User::where('email', $data['email'])->first()) {
            AuditLog::create(['user_id' => $user->id, 'user_name' => $user->name, 'municipality_id' => $user->municipality_id,
                'action' => AuditAction::PasswordResetRequested, 'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255)]);
        }

        // Mesma mensagem sempre: não revela quais e-mails estão cadastrados.
        return back()->with('ok', 'Se o e-mail estiver cadastrado, enviaremos um link para redefinir a senha (válido por 60 minutos).');
    }

    public function form(Request $request, string $token)
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'token' => ['required'], 'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ], ['password.confirmed' => 'A confirmação não confere com a nova senha.'], ['password' => 'nova senha', 'email' => 'e-mail']);

        $status = Password::reset(
            [...$request->only('email', 'token', 'password', 'password_confirmation'), 'active' => true],
            function (User $user, string $password) use ($request) {
                $user->forceFill([
                    'password' => $password, 'must_change_password' => false, 'remember_token' => Str::random(60),
                ])->save();
                // Derruba sessões abertas: quem tinha a senha antiga é desconectado.
                DB::table('sessions')->where('user_id', $user->id)->delete();
                AuditLog::create(['user_id' => $user->id, 'user_name' => $user->name, 'municipality_id' => $user->municipality_id,
                    'action' => AuditAction::PasswordReset, 'ip' => $request->ip(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 255)]);
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
        }

        // A redefinição não pula o 2FA: o próximo login ainda exige o código.
        return redirect()->route('login')->with('ok', 'Senha redefinida. Entre com a nova senha.');
    }
}
