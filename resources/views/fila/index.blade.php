@extends('layouts.app')
@section('title', 'Lista de espera')
@section('content')
<h1>Lista de espera</h1>
<p class="muted">Casos abertos <strong>sem escuta marcada nem realizada</strong>. A ordem é sempre a mesma e explicável: urgentes, depois violência sexual, depois meta da 1ª escuta vencida, e por fim quem espera há mais tempo.</p>

<div class="row" style="margin-bottom:14px;justify-content:flex-start">
    @foreach (\App\Services\WaitingList::FILTERS as $key => $label)
        <a class="btn {{ $filter === $key ? '' : 'ghost' }}" style="flex:0;white-space:nowrap" href="{{ route('fila.index', ['filtro' => $key]) }}">{{ $label }} ({{ $counts[$key] }})</a>
    @endforeach
</div>

@if ($canAssign && $workload->isNotEmpty())
<div class="card">
    <h2>Carga da equipe</h2>
    <table>
        <tr><th>Profissional</th><th>Casos abertos</th><th>Aguardando 1ª escuta</th><th>Escutas nos próximos 7 dias</th></tr>
        @foreach ($workload as $w)
            <tr><td>{{ $w['user']->name }} <span class="muted">{{ $w['user']->role->label() }}</span></td><td>{{ $w['open'] }}</td><td>{{ $w['waiting'] }}</td><td>{{ $w['upcoming'] }}</td></tr>
        @endforeach
    </table>
</div>
@endif

@if ($canAssign)<form method="post" action="{{ route('fila.assign') }}">@csrf @endif
<div class="card">
    <table>
        <tr>@if ($canAssign)<th></th>@endif<th>Protocolo</th><th>Criança/adolescente</th><th>Prioridade</th><th>Espera</th><th>Meta da 1ª escuta</th><th>Responsável</th>@unless ($canAssign)<th></th>@endunless</tr>
        @forelse ($cases as $c)
            @php $overdue = $list->overdue($c); @endphp
            <tr>
                @if ($canAssign)<td><input type="checkbox" name="ids[]" value="{{ $c->id }}" aria-label="Selecionar {{ $c->protocol }}"></td>@endif
                <td><a href="{{ route('casos.show', $c) }}">{{ $c->protocol }}</a></td>
                <td>{{ $c->child->displayName() }} @if ($c->child->age !== null)<span class="muted">({{ $c->child->age }} anos)</span>@endif</td>
                <td>@forelse ($list->reasons($c) as $r)<span class="badge {{ in_array($r, ['Urgente', 'Meta vencida']) ? 'urgent' : 'warn' }}">{{ $r }}</span> @empty <span class="muted">—</span>@endforelse</td>
                <td>{{ $list->waitingDays($c) }} dia(s)</td>
                <td>{{ $list->dueDate($c)->format('d/m/Y') }}</td>
                <td>{{ $c->responsible?->name ?? '—' }}</td>
                @unless ($canAssign)
                    <td>@can('claim', $c)<button form="claim-{{ $c->id }}" class="ghost">Assumir</button>@endcan</td>
                @endunless
            </tr>
        @empty
            <tr><td colspan="8" class="muted">Ninguém na lista de espera neste filtro.</td></tr>
        @endforelse
    </table>
</div>

@if ($canAssign && $cases->isNotEmpty())
    <div class="card row">
        <div style="flex:2"><label>Atribuir os casos selecionados a</label>
            <select name="responsible_id" required>
                <option value="">Escolha o profissional…</option>
                @foreach ($workload as $w)
                    <option value="{{ $w['user']->id }}">{{ $w['user']->name }} — {{ $w['open'] }} abertos, {{ $w['waiting'] }} aguardando</option>
                @endforeach
            </select></div>
        <div><button>Atribuir selecionados</button></div>
    </div>
    <p class="muted">O profissional recebe um e-mail só com os protocolos. Casos já com ele são ignorados.</p>
@endif
@if ($canAssign)</form>@endif

@unless ($canAssign)
    @foreach ($cases as $c)
        @can('claim', $c)<form id="claim-{{ $c->id }}" method="post" action="{{ route('casos.claim', $c) }}">@csrf</form>@endcan
    @endforeach
@endunless
@endsection
