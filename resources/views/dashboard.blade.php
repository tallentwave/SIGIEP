@extends('layouts.app')
@section('title', 'Painel')
@section('content')
<h1>Painel</h1>
@if (! empty($setup))
    <div class="errors" style="border-color:var(--warn);color:var(--warn)"><strong>Configuração do município pendente:</strong> {{ implode(' · ', $setup) }}.
        Peça ao administrador (código IBGE e unidade do SINAN em <em>Municípios</em>) e cadastre o <a href="{{ route('admin.municipios.fluxo', auth()->user()->municipality_id) }}">fluxo municipal</a>.</div>
@endif
<div class="grid" style="margin-bottom:16px">
    <div class="card stat"><b>{{ $open }}</b><span class="muted">casos em andamento</span></div>
    <div class="card stat"><b style="color:var(--bad)">{{ $urgent }}</b><span class="muted">casos urgentes</span></div>
    <div class="card stat"><b>{{ $awaiting }}</b><span class="muted">aguardando 1ª escuta</span></div>
    <div class="card stat"><b style="color:var(--warn)">{{ $queue }}</b><span class="muted">na fila (sem responsável) · <a href="{{ route('fila.index', ['filtro' => 'sem_responsavel']) }}">gerenciar</a></span></div>
</div>
<div class="grid wide">
    <div class="card">
        <h2>Próximas escutas</h2>
        @forelse ($upcoming as $s)
            <p><strong>{{ $s->scheduled_at->format('d/m H:i') }}</strong> · <a href="{{ route('casos.show', $s->case) }}">{{ $s->case->protocol }}</a>
            · {{ $s->case->child->displayName() }}<br><span class="muted">{{ $s->professional->name }}{{ $s->room ? ' · '.$s->room : '' }}</span></p>
        @empty <p class="muted">Nenhuma escuta agendada.</p> @endforelse
    </div>
    <div class="card">
        <h2>Encaminhamentos sem retorno</h2>
        @forelse ($overdueReferrals as $r)
            <p><span class="badge warn">venceu {{ $r->due_at->format('d/m') }}</span>
            <a href="{{ route('casos.show', $r->case) }}">{{ $r->case->protocol }}</a> → {{ $r->destination->label() }}</p>
        @empty <p class="muted">Nada pendente de cobrança.</p> @endforelse
    </div>
</div>
@endsection
