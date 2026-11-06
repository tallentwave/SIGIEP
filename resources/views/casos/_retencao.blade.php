{{-- Guarda legal e anonimização (coordenação). --}}
<div class="card">
    <h2>Retenção de dados</h2>
    <p class="muted">Prazo do município: {{ $case->municipality->retention_years }} anos após o encerramento
        @if ($case->closed_at)· vence em {{ app(\App\Services\Anonymizer::class)->dueDate($case)->format('d/m/Y') }}@else· ainda não encerrado, prazo não corre @endif.</p>

    @if ($case->legal_hold)
        <form method="post" action="{{ route('retencao.hold', $case) }}">@csrf
            <p><span class="badge warn">Guarda legal</span> {{ $case->legal_hold_reason }}</p>
            <button class="ghost">Retirar guarda legal</button>
        </form>
    @else
        <form method="post" action="{{ route('retencao.hold', $case) }}" class="row">@csrf
            <div style="flex:3"><label>Guarda legal (suspende a anonimização). Motivo: processo em curso, requisição do MP/Juízo…</label><input type="text" name="reason" required minlength="10" maxlength="255"></div>
            <div><button class="ghost">Aplicar guarda legal</button></div>
        </form>
    @endif

    @if ($case->status === \App\Enums\CaseStatus::Encerrado && ! $case->legal_hold)
        <details style="margin-top:14px"><summary style="color:var(--bad);cursor:pointer">Anonimizar agora (irreversível)</summary>
            <form method="post" action="{{ route('retencao.anonymize', $case) }}" style="margin-top:10px">@csrf
                <p class="muted">Remove nomes, documentos, endereços, relatos, textos livres e responsáveis. Mantém apenas dados estatísticos. <strong>Não há como desfazer.</strong></p>
                <p><label>Justificativa *</label><input type="text" name="reason" required minlength="10" maxlength="255"></p>
                <p><label>Digite o protocolo <strong>{{ $case->protocol }}</strong> para confirmar</label><input type="text" name="confirm" required autocomplete="off"></p>
                <button style="background:var(--bad)">Anonimizar definitivamente</button>
            </form>
        </details>
    @endif
</div>
