@extends('layouts.app')
@section('title', 'Casos')
@section('content')
<h1>Casos</h1>
<form class="card row" method="get">
    <div><label>Busca (protocolo ou nome)</label><input type="text" name="q" value="{{ request('q') }}"></div>
    <div><label>Situação</label>
        <select name="status"><option value="">Todas</option>
            @foreach ($statuses as $s)<option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>@endforeach
        </select></div>
    <div class="check"><input type="checkbox" id="urgent" name="urgent" value="1" @checked(request()->boolean('urgent'))><label for="urgent" style="margin:0;color:inherit">Só urgentes</label></div>
    <div><button>Filtrar</button></div>
</form>
<div class="card">
<table>
    <tr><th>Protocolo</th><th>Criança/adolescente</th><th>Recebido</th><th>Situação</th><th>Responsável</th></tr>
    @forelse ($cases as $c)
        <tr>
            <td><a href="{{ route('casos.show', $c) }}">{{ $c->protocol }}</a> @if ($c->urgent)<span class="badge urgent">URGENTE</span>@endif</td>
            <td>{{ $c->child->displayName() }} @if ($c->child->age !== null)<span class="muted">({{ $c->child->age }} anos)</span>@endif</td>
            <td>{{ $c->received_at->format('d/m/Y') }}</td>
            <td><span class="badge">{{ $c->status->label() }}</span></td>
            <td>{{ $c->responsible?->name ?? '—' }}</td>
        </tr>
    @empty
        <tr><td colspan="5" class="muted">Nenhum caso encontrado.</td></tr>
    @endforelse
</table>
{{ $cases->links() }}
</div>
@endsection
