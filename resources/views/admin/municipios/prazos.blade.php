@extends('layouts.app')
@section('title', 'Prazos · '.$municipality->name)
@section('content')
<h1>Prazos · {{ $municipality->fullName() }}</h1>
<p class="muted">Definem quando o painel e os e-mails diários passam a cobrar a equipe. Confirme os valores com a vigilância epidemiológica e a coordenação do município. As mudanças valem para todos os casos, inclusive os já cadastrados.</p>
<form class="card" method="post" action="{{ route('admin.municipios.prazos.update', $municipality) }}">
    @csrf @method('PUT')
    <h2>Notificação ao SINAN (dias após o recebimento do caso)</h2>
    <div class="grid">
        <div><label>Violência sexual (notificação imediata) *</label><input type="number" name="sinan_deadline_sexual_days" min="0" max="60" value="{{ old('sinan_deadline_sexual_days', $municipality->sinan_deadline_sexual_days) }}" required>
            <span class="muted">0 = no mesmo dia</span></div>
        <div><label>Demais violências *</label><input type="number" name="sinan_deadline_default_days" min="0" max="60" value="{{ old('sinan_deadline_default_days', $municipality->sinan_deadline_default_days) }}" required></div>
    </div>
    <h2 style="margin-top:16px">Lista de espera</h2>
    <div class="grid">
        <div><label>Meta: dias até a 1ª escuta marcada ou realizada *</label><input type="number" name="first_listening_days" min="1" max="60" value="{{ old('first_listening_days', $municipality->first_listening_days) }}" required>
            <span class="muted">Casos além da meta aparecem como atrasados na lista de espera e nos alertas.</span></div>
        <div><label>Dias sem responsável até alertar a coordenação *</label><input type="number" name="queue_alert_days" min="1" max="60" value="{{ old('queue_alert_days', $municipality->queue_alert_days) }}" required></div>
    </div>
    <p style="margin-top:16px"><button>Salvar prazos</button></p>
</form>
<div class="card muted">Exemplo com os valores salvos: um caso de violência sexual recebido hoje deve ser notificado até
    <strong>{{ now()->addDays($municipality->sinan_deadline_sexual_days)->format('d/m/Y') }}</strong>; os demais, até
    <strong>{{ now()->addDays($municipality->sinan_deadline_default_days)->format('d/m/Y') }}</strong>.</div>
@endsection
