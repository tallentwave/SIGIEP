<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('admin');

        return view('admin.users.index', [
            'users' => User::with('municipality')
                ->when($request->filled('municipality'), fn ($q) => $q->where('municipality_id', $request->integer('municipality')))
                ->orderBy('name')->paginate(30)->withQueryString(),
            'municipalities' => Municipality::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        $this->authorize('admin');

        return view('admin.users.form', $this->formData(new User(['active' => true])));
    }

    public function store(Request $request)
    {
        $this->authorize('admin');
        $data = $request->validate([...$this->rules(), 'password' => ['required', Password::min(10)->letters()->numbers()]]);

        // Senha definida pelo admin é provisória: o usuário troca no primeiro acesso.
        User::create([...$data, 'must_change_password' => true]);

        return redirect()->route('admin.usuarios.index')->with('ok', 'Usuário criado. Ele deverá trocar a senha no primeiro acesso.');
    }

    public function edit(User $user)
    {
        $this->authorize('admin');

        return view('admin.users.form', $this->formData($user));
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('admin');
        $data = $request->validate([
            ...$this->rules($user),
            'password' => ['nullable', Password::min(10)->letters()->numbers()],
        ]);

        // Evita o admin se trancar para fora ou rebaixar o último admin.
        if ($user->is($request->user()) && ($data['role'] !== Role::Administrador->value || ! ($data['active'] ?? false))) {
            return back()->withErrors(['role' => 'Você não pode remover seu próprio acesso de administrador.'])->withInput();
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        } else {
            $data['must_change_password'] = true;
        }
        $user->update($data);

        return redirect()->route('admin.usuarios.index')->with('ok', 'Usuário atualizado.');
    }

    private function rules(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::enum(Role::class)],
            // Todo perfil, exceto Administrador, pertence a um município.
            'municipality_id' => [Rule::requiredIf(fn () => request('role') !== Role::Administrador->value),
                'nullable', Rule::exists('municipalities', 'id')],
            'registro_profissional' => ['nullable', 'string', 'max:50'],
            'active' => ['boolean'],
        ];
    }

    private function formData(User $user): array
    {
        return ['user' => $user, 'roles' => Role::cases(), 'municipalities' => Municipality::orderBy('name')->get()];
    }
}
