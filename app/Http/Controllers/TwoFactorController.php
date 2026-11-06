<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Services\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function __construct(private TwoFactor $tf) {}

    /** Situação do 2FA do usuário logado. */
    public function manage(Request $request)
    {
        return view('2fa.manage', ['user' => $request->user(), 'codes' => session('recovery_codes')]);
    }

    public function setup(Request $request)
    {
        $user = $request->user();
        if ($user->hasTwoFactor()) {
            return redirect()->route('2fa.manage');
        }
        if (! $user->two_factor_secret) {
            $user->forceFill(['two_factor_secret' => $this->tf->newSecret()])->save();
        }

        return view('2fa.setup', ['secret' => $user->two_factor_secret, 'qr' => $this->tf->qrSvg($user, $user->two_factor_secret)]);
    }

    public function confirm(Request $request)
    {
        $request->validate(['code' => ['required', 'string']], [], ['code' => 'código']);
        $user = $request->user();
        abort_if($user->hasTwoFactor() || ! $user->two_factor_secret, 403);

        $step = $this->tf->verify($user->two_factor_secret, $request->string('code'));
        if ($step === null) {
            return back()->withErrors(['code' => 'Código inválido. Confira a hora do celular e tente de novo.']);
        }

        [$plain, $hashes] = $this->tf->newRecoveryCodes();
        $user->forceFill([
            'two_factor_confirmed_at' => now(), 'two_factor_last_step' => $step, 'two_factor_recovery_codes' => $hashes,
        ])->save();
        $request->session()->put('2fa_passed', true);
        AuditLog::record(AuditAction::TwoFactorEnabled, $user);

        return redirect()->route('2fa.manage')->with('recovery_codes', $plain)
            ->with('ok', '2FA ativado. Guarde os códigos de recuperação abaixo: eles não serão mostrados de novo.');
    }

    public function challenge(Request $request)
    {
        if (! $request->user()->hasTwoFactor() || $request->session()->get('2fa_passed')) {
            return redirect()->route('dashboard');
        }

        return view('2fa.challenge');
    }

    public function verify(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasTwoFactor(), 403);
        $request->validate(['code' => ['nullable', 'string', 'max:32'], 'recovery_code' => ['nullable', 'string', 'max:32']]);

        $ok = false;
        if ($request->filled('code')) {
            $step = $this->tf->verify($user->two_factor_secret, $request->string('code'), $user->two_factor_last_step);
            if ($step !== null) {
                $user->forceFill(['two_factor_last_step' => $step])->save();
                $ok = true;
            }
        } elseif ($request->filled('recovery_code')) {
            if ($this->tf->useRecoveryCode($user, $request->string('recovery_code'))) {
                AuditLog::record(AuditAction::TwoFactorRecoveryUsed, $user);
                $ok = true;
            }
        }

        if (! $ok) {
            AuditLog::record(AuditAction::TwoFactorFailed, $user);

            return back()->withErrors(['code' => 'Código inválido ou já utilizado.']);
        }

        $request->session()->put('2fa_passed', true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function regenerate(Request $request)
    {
        $this->confirmPassword($request);
        abort_unless($request->user()->hasTwoFactor(), 403);

        [$plain, $hashes] = $this->tf->newRecoveryCodes();
        $request->user()->forceFill(['two_factor_recovery_codes' => $hashes])->save();

        return redirect()->route('2fa.manage')->with('recovery_codes', $plain)->with('ok', 'Novos códigos gerados; os anteriores deixaram de valer.');
    }

    public function disable(Request $request)
    {
        $this->confirmPassword($request);
        $user = $request->user();
        abort_if($user->requiresTwoFactor(), 403, 'Seu perfil exige 2FA.');

        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->save();
        $request->session()->forget('2fa_passed');
        AuditLog::record(AuditAction::TwoFactorDisabled, $user);

        return redirect()->route('2fa.manage')->with('ok', '2FA desativado.');
    }

    private function confirmPassword(Request $request): void
    {
        $request->validate(['password' => ['required', 'string']], [], ['password' => 'senha']);
        if (! Hash::check($request->string('password'), $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Senha incorreta.']);
        }
    }
}
