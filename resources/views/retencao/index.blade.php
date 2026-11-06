@extends('layouts.app')
@section('title', 'Retenção de dados')
@section('content')
<h1>Retenção e anonimização · {{ $municipality->fullName() }}</h1>
@if ($municipalities->isNotEmpty())
<form class="card row" method="get"><div><label>Município</label><select name="municipio">@foreach ($municipalities as $m)<option value="{{ $m->id }}" @selected($m->id === $municipality->id)>{{ $m->fullName() }}</option>@endforeach</select></div><div><button>Ver</button></div></form>
@endif
<div class="grid" style="margin-bottom:16px">
    <div class="card stat"><b>{{ $municipality->retention_years }}</b><span class="muted">anos de guarda após o encerramento</span></div>
    <div class="card stat"><b style="color:var(--warn)">{{ $eligible }}</b><span class="muted">vencidos, prontos para anonimizar</span></div>
    <div class="card stat"><b>{{ $dueSoon->count() }}</b><span class="muted">vencem em 90 dias</span></div>
    <div class="card stat"><b>{{ $anonymized }}</b><span class="muted">já anonimizados</span></div>
</div>
<div class="card">
    <p>A anonimização remove nomes, documentos, endereços, relatos, textos livres e responsáveis, e <strong>mantém só o que é estatístico</strong>. É irreversível.
       Casos sob <strong>guarda legal</strong> nunca são anonimizados. Execução automática: <strong>{{ $auto ? 'ligada (todo dia 1º)' : 'desligada' }}</strong>.
       Rodar manualmente: <code>php artisan sigiep:retencao --dry-run</code> (simula) e <code>php artisan sigiep:retencao</code>. Trilha de auditoria: {{ $auditYears ? "expurgada após {$auditYears} anos" : 'sem expurgo' }}.</p>
    <p class="muted">O prazo ({{ $municipality->retention_years }} anos) é um padrão provisório: valide-o com a assessoria jurídica e o encarregado de dados (DPO) e peça ao administrador para ajustá-lo em Municípios.</p>
</div>
<div class="card"><h2>Vencem nos próximos 90 dias</h2>
<table><tr><th>Protocolo</th><th>Encerrado</th><th>Vence</th></tr>
@forelse ($dueSoon as $c)<tr><td><a href="{{ route('casos.show', $c) }}">{{ $c->protocol }}</a>@if ($c->legal_hold) <span class="badge warn">guarda legal</span>@endif</td><td>{{ $c->closed_at->format('d/m/Y') }}</td><td>{{ $anonymizer->dueDate($c->setRelation('municipality', $municipality))->format('d/m/Y') }}</td></tr>
@empty<tr><td colspan="3" class="muted">Nenhum.</td></tr>@endforelse</table></div>
<div class="card"><h2>Sob guarda legal</h2>
<table><tr><th>Protocolo</th><th>Motivo</th><th>Desde</th></tr>
@forelse ($held as $c)<tr><td><a href="{{ route('casos.show', $c) }}">{{ $c->protocol }}</a></td><td>{{ $c->legal_hold_reason }}</td><td>{{ $c->legal_hold_at?->format('d/m/Y') }}</td></tr>
@empty<tr><td colspan="3" class="muted">Nenhum.</td></tr>@endforelse</table></div>
@endsection
