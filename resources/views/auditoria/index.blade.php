@extends('layouts.app')
@section('title', 'Auditoria')
@section('content')
<h1>Trilha de auditoria</h1>
<form class="card row" method="get">
    <div><label>Ação</label><select name="action"><option value="">Todas</option>
        @foreach ($actions as $a)<option value="{{ $a->value }}" @selected(request('action') === $a->value)>{{ $a->label() }}</option>@endforeach</select></div>
    <div><label>ID do caso</label><input type="number" name="case_id" value="{{ request('case_id') }}"></div>
    <div><button>Filtrar</button></div>
</form>
<div class="card">
<table>
    <tr><th>Quando</th><th>Usuário</th><th>Ação</th><th>Objeto</th><th>Caso</th><th>IP</th></tr>
    @foreach ($logs as $l)
        <tr>
            <td>{{ $l->created_at->format('d/m/Y H:i:s') }}</td>
            <td>{{ $l->user?->name ?? $l->user_name ?? '—' }}</td>
            <td><span class="badge {{ in_array($l->action->value, ['acesso_negado','login_falhou']) ? 'urgent' : '' }}">{{ $l->action->label() }}</span>
                @if ($l->changes) <span class="muted">{{ json_encode($l->changes, JSON_UNESCAPED_UNICODE) }}</span>@endif</td>
            <td>{{ $l->subject_type ? class_basename($l->subject_type).' #'.$l->subject_id : '—' }}</td>
            <td>{{ $l->case_id ?? '—' }}</td><td>{{ $l->ip }}</td>
        </tr>
    @endforeach
</table>
{{ $logs->links() }}
</div>
@endsection
