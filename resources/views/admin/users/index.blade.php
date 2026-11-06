@extends('layouts.app')
@section('title', 'Usuários')
@section('content')
<h1>Usuários <a class="btn" style="float:right" href="{{ route('admin.usuarios.create') }}">Novo usuário</a></h1>
<form class="card row" method="get">
    <div><label>Município</label><select name="municipality"><option value="">Todos</option>
        @foreach ($municipalities as $m)<option value="{{ $m->id }}" @selected((int) request('municipality') === $m->id)>{{ $m->fullName() }}</option>@endforeach</select></div>
    <div><button>Filtrar</button></div>
</form>
<div class="card"><table>
    <tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Município</th><th>Situação</th><th></th></tr>
    @foreach ($users as $u)
        <tr><td>{{ $u->name }}</td><td>{{ $u->email }}</td><td>{{ $u->role->label() }}</td><td>{{ $u->municipality?->fullName() ?? '—' }}</td>
            <td>@if ($u->active)<span class="badge">ativo</span>@else<span class="badge warn">inativo</span>@endif @if ($u->must_change_password)<span class="badge">senha provisória</span>@endif</td>
            <td><a href="{{ route('admin.usuarios.edit', $u) }}">Editar</a></td></tr>
    @endforeach
</table>{{ $users->links() }}</div>
@endsection
