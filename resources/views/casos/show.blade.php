@extends('layouts.app')
@section('title', $case->protocol)
@section('content')
<h1>{{ $case->protocol }} @if ($case->urgent)<span class="badge urgent">URGENTE</span>@endif <span class="badge">{{ $case->status->label() }}</span></h1>

<div class="grid wide">
<div class="card">
    <h2>Criança / adolescente <a class="btn ghost" style="float:right;padding:2px 10px" href="{{ route('casos.edit', $case) }}">Editar</a></h2>
    <dl>
        <dt>Nome</dt><dd>{{ $case->child->name }}@if ($case->child->social_name) ({{ $case->child->social_name }})@endif</dd>
        <dt>Nascimento</dt><dd>{{ $case->child->birth_date?->format('d/m/Y') ?? '—' }}@if ($case->child->age !== null) · {{ $case->child->age }} anos{{ $case->child->birth_date_estimated ? ' (estimada)' : '' }}@endif</dd>
        <dt>Mãe</dt><dd>{{ $case->child->mother_name ?? '—' }}</dd>
        <dt>Escola</dt><dd>{{ $case->child->school ?? '—' }}</dd>
        <dt>Endereço</dt><dd>{{ collect([$case->child->address, $case->child->neighborhood, $case->child->city])->filter()->implode(', ') ?: '—' }}</dd>
        @if ($case->child->has_disability)<dt>Deficiência</dt><dd>{{ $case->child->disability_notes ?: 'Sim' }}</dd>@endif
    </dl>
    <h2 style="margin-top:16px">Responsáveis</h2>
    @forelse ($case->child->guardians as $g)
        <p>{{ $g->name }} <span class="muted">{{ $g->relationship }} · {{ $g->phone }}</span>
            @if ($g->is_suspected)<span class="badge urgent">suposto autor</span>@endif
            @unless ($g->can_be_contacted)<span class="badge warn">não contatar</span>@endunless</p>
    @empty <p class="muted">Nenhum responsável cadastrado.</p> @endforelse
</div>

<div class="card">
    <h2>Dados do caso</h2>
    <dl>
        <dt>Condição</dt><dd>{{ $case->condition->label() }}</dd>
        <dt>Violência</dt><dd>{{ collect($case->violence_types)->map(fn ($v) => \App\Enums\ViolenceType::from($v)->label())->implode(', ') }}</dd>
        <dt>Órgão</dt><dd>{{ $case->origin }}{{ $case->origin_document ? ' · '.$case->origin_document : '' }}</dd>
        <dt>Recebido em</dt><dd>{{ $case->received_at->format('d/m/Y') }}</dd>
        <dt>Data do fato</dt><dd>{{ $case->violence_date?->format('d/m/Y') ?? '—' }}</dd>
        <dt>Responsável</dt><dd>{{ $case->responsible?->name ?? 'Não definido' }}</dd>
        <dt>Equipe</dt><dd>{{ $case->team->pluck('name')->implode(', ') ?: '—' }}</dd>
    </dl>
    @if ($canReadReports && $case->initial_report)
        <h2 style="margin-top:16px">Relato do órgão demandante</h2>
        <div class="sigilo"><pre class="report">{{ $case->initial_report }}</pre></div>
    @endif
</div>
</div>

@if ($canManage)
<form class="card" method="post" action="{{ route('casos.manage', $case) }}">
    @csrf @method('PUT')
    <h2>Gestão do caso (coordenação)</h2>
    <div class="row">
        <div><label>Responsável</label>
            <select name="responsible_id"><option value="">—</option>
                @foreach ($professionals as $p)<option value="{{ $p->id }}" @selected($case->responsible_id === $p->id)>{{ $p->name }}</option>@endforeach
            </select></div>
        <div><label>Equipe com acesso (Ctrl para vários)</label>
            <select name="team[]" multiple size="4">
                @foreach ($allUsers as $u)<option value="{{ $u->id }}" @selected($case->team->contains($u))>{{ $u->name }} — {{ $u->role->label() }}</option>@endforeach
            </select></div>
        <div><label>Situação</label>
            <select name="status">@foreach ($statuses as $s)<option value="{{ $s->value }}" @selected($case->status === $s)>{{ $s->label() }}</option>@endforeach</select></div>
        <div><button>Salvar</button></div>
    </div>
</form>
@endif

<div class="card">
    <h2>Escutas especializadas</h2>
    <table>
        <tr><th>Data</th><th>Profissional</th><th>Sala</th><th>Situação</th><th></th></tr>
        @forelse ($case->sessions->sortByDesc('scheduled_at') as $s)
            <tr>
                <td>{{ $s->scheduled_at->format('d/m/Y H:i') }} <span class="muted">({{ $s->duration_minutes }} min)</span></td>
                <td>{{ $s->professional->name }}</td><td>{{ $s->room ?? '—' }}</td>
                <td><span class="badge">{{ $s->status->label() }}</span>@if ($s->recorded) <span class="badge">gravada</span>@endif</td>
                <td><a href="{{ route('escutas.edit', $s) }}">{{ $canReadReports ? 'Abrir / relatar' : 'Reagendar' }}</a></td>
            </tr>
        @empty <tr><td colspan="5" class="muted">Nenhuma escuta agendada.</td></tr> @endforelse
    </table>
    <form method="post" action="{{ route('escutas.store', $case) }}" style="margin-top:14px">
        @csrf
        <h2>Agendar escuta</h2>
        <div class="row">
            <div><label>Profissional</label><select name="professional_id" required>
                @foreach ($professionals as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->role->label() }})</option>@endforeach</select></div>
            <div><label>Data e hora</label><input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" required></div>
            <div><label>Duração (min)</label><input type="number" name="duration_minutes" value="{{ old('duration_minutes', 60) }}" min="15" max="240"></div>
            <div><label>Sala</label><input type="text" name="room" value="{{ old('room') }}"></div>
            <div><button>Agendar</button></div>
        </div>
    </form>
</div>

<div class="card">
    <h2>Encaminhamentos e rede de proteção</h2>
    <table>
        <tr><th>Destino</th><th>Enviado / prazo</th><th>Situação</th><th>Atualizar</th></tr>
        @forelse ($case->referrals as $r)
            <tr>
                <td>{{ $r->destination->label() }}@if ($r->destination_detail) — {{ $r->destination_detail }}@endif<br><span class="muted">{{ $r->document_number }}</span></td>
                <td>{{ $r->sent_at?->format('d/m/Y') ?? '—' }} / {{ $r->due_at?->format('d/m/Y') ?? '—' }} @if ($r->isOverdue())<span class="badge warn">atrasado</span>@endif</td>
                <td><span class="badge">{{ $r->status->label() }}</span>@if ($r->returned_at)<br><span class="muted">retorno {{ $r->returned_at->format('d/m/Y') }}</span>@endif</td>
                <td>
                    <form method="post" action="{{ route('encaminhamentos.update', $r) }}" class="row">
                        @csrf @method('PATCH')
                        <select name="status">@foreach ($referralStatuses as $s)<option value="{{ $s->value }}" @selected($r->status === $s)>{{ $s->label() }}</option>@endforeach</select>
                        <button class="ghost">OK</button>
                    </form>
                </td>
            </tr>
        @empty <tr><td colspan="4" class="muted">Nenhum encaminhamento registrado.</td></tr> @endforelse
    </table>
    <form method="post" action="{{ route('encaminhamentos.store', $case) }}" style="margin-top:14px">
        @csrf
        <h2>Novo encaminhamento</h2>
        <div class="row">
            <div><label>Destino</label><select name="destination" required>@foreach ($destinations as $d)<option value="{{ $d->value }}">{{ $d->label() }}</option>@endforeach</select></div>
            <div><label>Unidade / detalhe</label><input type="text" name="destination_detail"></div>
            <div><label>Nº do ofício</label><input type="text" name="document_number"></div>
            <div><label>Data de envio</label><input type="date" name="sent_at"></div>
            <div><label>Prazo p/ retorno</label><input type="date" name="due_at"></div>
        </div>
        <p><label>Resumo do encaminhamento (sigiloso)</label><textarea name="summary" style="min-height:70px"></textarea></p>
        <button>Registrar</button>
    </form>
</div>
@endsection
