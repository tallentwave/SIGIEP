@extends('layouts.app')
@section('title', 'Integrações')
@section('content')
<h1>Integrações · SINAN</h1>
<div class="card">
    <p>Casos de vítimas ainda <strong>sem notificação concluída ou dispensada</strong>, em ordem de prazo.
       O SINAN não recebe envios pela internet: gere o arquivo, entregue à vigilância epidemiológica de {{ $municipality->name }} e registre aqui o nº da notificação devolvido.</p>
    @if (blank($municipality->ibge_code) || blank($municipality->sinan_unit_name))
        <div class="errors">Antes de gerar: peça ao administrador para informar, em <em>Municípios</em>, o código IBGE e a unidade notificadora (tipo, nome e código).</div>
    @endif
    <form method="post" action="{{ route('integracoes.sinan.file') }}">@csrf
    <table>
        <tr><th></th><th>Protocolo</th><th>Recebido</th><th>Prazo</th><th>Violência</th></tr>
        @forelse ($pending as $c)
            @php $dl = $exporter::deadline($c); @endphp
            <tr><td><input type="checkbox" name="ids[]" value="{{ $c->id }}"></td>
                <td><a href="{{ route('casos.show', $c) }}">{{ $c->protocol }}</a>@if ($c->urgent) <span class="badge urgent">URGENTE</span>@endif</td>
                <td>{{ $c->received_at->format('d/m/Y') }}</td>
                <td>{{ $dl->format('d/m/Y') }} @if ($dl->isPast())<span class="badge urgent">atrasado</span>@endif</td>
                <td>{{ collect($c->violence_types)->map(fn ($v) => \App\Enums\ViolenceType::from($v)->label())->implode(', ') }}</td></tr>
        @empty<tr><td colspan="5" class="muted">Nada pendente.</td></tr>@endforelse
    </table>
    @if ($pending->isNotEmpty())<p><button>Gerar arquivo SINAN dos selecionados</button></p>@endif
    </form>
</div>
<div class="card"><h2>Arquivos gerados aguardando nº da notificação</h2>
<table><tr><th>Protocolo</th><th>Gerado em</th></tr>
@forelse ($waiting as $d)<tr><td><a href="{{ route('casos.show', $d->case) }}">{{ $d->case->protocol }}</a></td><td>{{ $d->created_at->format('d/m/Y H:i') }}</td></tr>
@empty<tr><td colspan="2" class="muted">Nenhum.</td></tr>@endforelse</table>
<p class="muted">Registre o número na página do caso. Prazos internos: violência sexual {{ $municipality->sinan_deadline_sexual_days }} dia(s); demais {{ $municipality->sinan_deadline_default_days }} dias (<a href="{{ route('admin.municipios.prazos', $municipality) }}">alterar</a>). Confirme com a vigilância epidemiológica.</p></div>
@endsection
