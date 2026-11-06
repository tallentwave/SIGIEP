<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function edit()
    {
        return view('perfil.senha');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(10)->letters()->numbers()],
        ], [
            'current_password.current_password' => 'A senha atual está incorreta.',
            'password.confirmed' => 'A confirmação não confere com a nova senha.',
            'password.different' => 'A nova senha deve ser diferente da atual.',
        ], ['current_password' => 'senha atual', 'password' => 'nova senha']);

        $request->user()->update(['password' => $data['password'], 'must_change_password' => false]);

        return redirect()->route('dashboard')->with('ok', 'Senha alterada.');
    }
}
