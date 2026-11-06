@extends('layouts.app')
@section('title', 'Usuário')
@section('content')
<h1>{{ $user->exists ? 'Editar usuário' : 'Novo usuário' }}</h1>
<form class="card" method="post" action="{{ $user->exists ? route('admin.usuarios.update', $user) : route('admin.usuarios.store') }}">
    @csrf @if ($user->exists) @method('PUT') @endif
    <div class="grid">
        <div><label>Nome *</label><input type="text" name="name" value="{{ old('name', $user->name) }}" required></div>
        <div><label>E-mail *</label><input type="email" name="email" value="{{ old('email', $user->email) }}" required></div>
        <div><label>Perfil *</label><select name="role">@foreach ($roles as $r)<option value="{{ $r->value }}" @selected(old('role', $user->role?->value) === $r->value)>{{ $r->label() }}</option>@endforeach</select></div>
        <div><label>Município (obrigatório, exceto Administrador)</label><select name="municipality_id"><option value="">—</option>
            @foreach ($municipalities as $m)<option value="{{ $m->id }}" @selected((int) old('municipality_id', $user->municipality_id) === $m->id)>{{ $m->fullName() }}</option>@endforeach</select></div>
        <div><label>Registro profissional (CRP / CRESS)</label><input type="text" name="registro_profissional" value="{{ old('registro_profissional', $user->registro_profissional) }}"></div>
        <div><label>{{ $user->exists ? 'Nova senha provisória (vazio = manter)' : 'Senha provisória *' }}</label><input type="password" name="password" autocomplete="new-password" @required(! $user->exists)></div>
    </div>
    <p class="muted">Mínimo 10 caracteres, com letras e números. O usuário é obrigado a trocar a senha no próximo acesso.</p>
    <div class="check"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked(old('active', $user->active))> Usuário ativo</div>
    <p><button>Salvar</button> <a class="btn ghost" href="{{ route('admin.usuarios.index') }}">Cancelar</a></p>
</form>
@endsection
