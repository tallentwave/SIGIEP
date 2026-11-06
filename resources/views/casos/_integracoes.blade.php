{{-- SINAN e Sistema de Justiça. Nenhum envio automático: gera arquivo/documento e registra a confirmação. --}}
@php
    use App\Enums\DispatchStatus as DS;
    use App\Enums\DispatchSystem as SYS;
    $sinan = $case->dispatches->where('system', SYS::Sinan)->sortByDesc('id');
    $sinanDone = $sinan->first(fn ($d) => in_array($d->status, [DS::Concluido, DS::Dispensado], true));
    $justica = $case->dispatches->where('system', SYS::Justica)->sortByDesc('id');
    $needsSinan = \App\Services\SinanExporter::requires($case);
@endphp
<div class="card">
    <h2>Integrações</h2>

    <h3 style="font-size:15px">SINAN · notificação de violência</h3>
    @if (! $needsSinan)
        <p class="muted">Caso de testemunha: a ficha de notificação individual não se aplica.</p>
    @elseif ($sinanDone?->status === DS::Concluido)
        <p><span class="badge">Notificado</span> nº {{ $sinanDone->reference }} em {{ $sinanDone->sent_at?->format('d/m/Y') }}</p>
    @elseif ($sinanDone?->status === DS::Dispensado)
        <p><span class="badge">Dispensado</span> {{ $sinanDone->summary }}</p>
    @else
        @php $deadline = \App\Services\SinanExporter::deadline($case); @endphp
        <p>Prazo interno: <strong>{{ $deadline->format('d/m/Y') }}</strong> @if ($deadline->isPast())<span class="badge urgent">atrasado</span>@endif
            <span class="muted">· o SINAN não tem API pública: o arquivo vai à vigilância epidemiológica do município, que informa o nº da notificação.</span></p>
        <form method="post" action="{{ route('integracoes.sinan.file') }}" style="display:inline">@csrf
            <input type="hidden" name="ids[]" value="{{ $case->id }}"><button>Gerar arquivo SINAN (CSV)</button></form>
        <form method="post" action="{{ route('integracoes.sinan.sheet', $case) }}" style="display:inline">@csrf
            <button class="ghost">Ficha para digitação (PDF)</button></form>
        <details style="margin-top:10px"><summary class="muted" style="cursor:pointer">Já foi notificado por outra unidade? Dispensar</summary>
            <form method="post" action="{{ route('integracoes.sinan.waive', $case) }}" class="row" style="margin-top:8px">@csrf
                <div style="flex:3"><label>Justificativa (ex.: notificado pela UBS em dd/mm, nº …)</label><input type="text" name="reason" required minlength="10" maxlength="500"></div>
                <div><button class="ghost">Dispensar</button></div></form></details>
    @endif
    @foreach ($sinan->where('status', DS::Gerado) as $d)
        <form method="post" action="{{ route('integracoes.confirm', $d) }}" class="row" style="margin-top:10px">@csrf
            <div style="flex:2"><span class="badge warn">arquivo gerado em {{ $d->created_at->format('d/m/Y H:i') }}</span><br><label>Nº da notificação devolvido pelo SINAN</label><input type="text" name="reference" inputmode="numeric" required></div>
            <div><label>Data da notificação</label><input type="date" name="sent_at" value="{{ now()->toDateString() }}" required></div>
            <div><button class="ghost">Registrar nº</button></div></form>
    @endforeach

    <h3 style="font-size:15px;margin-top:20px">Sistema de Justiça</h3>
    <p class="muted">A integração direta (MNI/CNJ) depende de adesão do tribunal. Aqui o sistema valida e registra o processo e gera a comunicação com o mínimo necessário (sem o relato da escuta).</p>
    @foreach ($case->processes as $p)
        <form method="post" action="{{ route('integracoes.processos.destroy', $p) }}" style="margin:4px 0">@csrf @method('DELETE')
            <code>{{ $p->number }}</code> <span class="muted">{{ \App\Support\CnjNumber::court($p->number) }}{{ $p->court_unit ? ' · '.$p->court_unit : '' }}</span> <button class="ghost" style="padding:0 8px">remover</button></form>
    @endforeach
    <form method="post" action="{{ route('integracoes.processos.store', $case) }}" class="row" style="margin-top:8px">@csrf
        <div><label>Nº do processo (CNJ)</label><input type="text" name="number" placeholder="0000000-00.0000.0.00.0000" required></div>
        <div><label>Vara / juízo</label><input type="text" name="court_unit"></div>
        <div><button class="ghost">Vincular processo</button></div></form>

    <details style="margin-top:14px"><summary style="cursor:pointer">Gerar comunicação ao Juízo (PDF)</summary>
        <form method="post" action="{{ route('integracoes.justica.pdf', $case) }}" style="margin-top:10px">@csrf
            <div class="row">
                <div><label>Processo</label><select name="judicial_process_id"><option value="">— sem processo —</option>@foreach ($case->processes as $p)<option value="{{ $p->id }}">{{ $p->number }}</option>@endforeach</select></div>
                <div><label>Destinatário</label><input type="text" name="recipient" placeholder="Vara da Infância e da Juventude…"></div>
            </div>
            <p><label>Síntese técnica (mínimo necessário; não cole o relato da escuta) *</label><textarea name="summary" required minlength="20" maxlength="4000" style="min-height:120px"></textarea></p>
            <button>Gerar PDF</button>
        </form></details>
    @foreach ($justica->where('status', DS::Gerado) as $d)
        <form method="post" action="{{ route('integracoes.confirm', $d) }}" class="row" style="margin-top:10px">@csrf
            <div style="flex:2"><span class="badge warn">comunicação gerada em {{ $d->created_at->format('d/m/Y H:i') }}</span><br><label>Nº do ofício / protocolo de envio</label><input type="text" name="reference" required></div>
            <div><label>Data do envio</label><input type="date" name="sent_at" value="{{ now()->toDateString() }}" required></div>
            <div><button class="ghost">Registrar envio</button></div></form>
    @endforeach
    @foreach ($justica->where('status', DS::Concluido) as $d)
        <p class="muted">Enviado em {{ $d->sent_at?->format('d/m/Y') }} · ref. {{ $d->reference }}{{ $d->process ? ' · proc. '.$d->process->number : '' }}</p>
    @endforeach
</div>
