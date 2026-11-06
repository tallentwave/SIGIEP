@extends('layouts.app')
@section('title', $child->displayName())
@section('content')
<h1>{{ $child->displayName() }}</h1>
<p class="muted">{{ $child->birth_date?->format('d/m/Y') ?? 'Nascimento não informado' }} · mãe: {{ $child->mother_name ?? '—' }}</p>
<div class="card">
    <h2>Casos <a class="btn" style="float:right" href="{{ route('casos.create', ['child' => $child->id]) }}">Novo caso para este atendido</a></h2>
    <table><tr><th>Protocolo</th><th>Recebido</th><th>Violência</th><th>Situação</th></tr>
    @foreach ($cases as $c)
        <tr><td><a href="{{ route('casos.show', $c) }}">{{ $c->protocol }}</a></td><td>{{ $c->received_at->format('d/m/Y') }}</td>
            <td>{{ collect($c->violence_types)->map(fn ($v) => \App\Enums\ViolenceType::from($v)->label())->implode(', ') }}</td><td><span class="badge">{{ $c->status->label() }}</span></td></tr>
    @endforeach</table>
    <p class="muted">Só aparecem os casos que seu perfil pode acessar.</p>
</div>
@endsection
