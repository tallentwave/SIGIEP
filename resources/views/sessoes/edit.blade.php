@extends('layouts.app')
@section('title', 'Escuta')
@section('content')
<h1>Escuta · <a href="{{ route('casos.show', $session->case) }}">{{ $session->case->protocol }}</a></h1>
<p class="muted">{{ $session->case->child->displayName() }}</p>
<form method="post" action="{{ route('escutas.update', $session) }}">
    @csrf @method('PUT')
    <div class="card">
        <h2>Agendamento</h2>
        <div class="row">
            <div><label>Profissional</label><select name="professional_id">@foreach ($professionals as $p)<option value="{{ $p->id }}" @selected($session->professional_id === $p->id)>{{ $p->name }}</option>@endforeach</select></div>
            <div><label>Data e hora</label><input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', $session->scheduled_at->format('Y-m-d\TH:i')) }}"></div>
            <div><label>Duração (min)</label><input type="number" name="duration_minutes" value="{{ old('duration_minutes', $session->duration_minutes) }}" min="15" max="240"></div>
            <div><label>Sala</label><input type="text" name="room" value="{{ old('room', $session->room) }}"></div>
            <div><label>Situação</label><select name="status">@foreach ($statuses as $s)<option value="{{ $s->value }}" @selected($session->status === $s)>{{ $s->label() }}</option>@endforeach</select></div>
        </div>
    </div>

    @if ($canRead)
    <div class="card sigilo">
        <h2>Registro da escuta (sigiloso)</h2>
        <p class="muted">Conteúdo armazenado criptografado. Cada leitura é registrada na auditoria.</p>
        <div class="row">
            <div><label>Acompanhante da criança</label><input type="text" name="accompanied_by" value="{{ old('accompanied_by', $session->accompanied_by) }}"></div>
            <div class="check"><input type="hidden" name="recorded" value="0"><input type="checkbox" name="recorded" value="1" @checked(old('recorded', $session->recorded))> Houve gravação audiovisual</div>
            <div><label>Local de custódia da mídia</label><input type="text" name="recording_location" value="{{ old('recording_location', $session->recording_location) }}"></div>
        </div>
        <p><label>Relato / síntese da escuta</label><textarea name="report" style="min-height:220px">{{ old('report', $session->report) }}</textarea></p>
        <p><label>Observações técnicas (comportamento, necessidades de proteção)</label><textarea name="technical_notes">{{ old('technical_notes', $session->technical_notes) }}</textarea></p>
    </div>
    @else
        <div class="card muted">Seu perfil agenda a escuta, mas não acessa o conteúdo do relato.</div>
    @endif

    <button>Salvar</button> <a class="btn ghost" href="{{ route('casos.show', $session->case) }}">Voltar</a>
</form>
@endsection
