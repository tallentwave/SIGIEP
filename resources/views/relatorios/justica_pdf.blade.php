<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#111} h1{font-size:15px;margin:6px 0} h2{font-size:12px;margin:14px 0 4px;border-bottom:1px solid #999}
table{width:100%;border-collapse:collapse} td,th{padding:3px 4px;border-bottom:1px solid #ddd;text-align:left;vertical-align:top} .box{border:2px solid #b42318;padding:6px;font-weight:bold}
pre{white-space:pre-wrap;font-family:inherit;margin:0} .sig{margin-top:40px}
</style></head><body>
<div class="box">DOCUMENTO SIGILOSO · Lei 13.431/2017 · destinado exclusivamente ao órgão indicado.</div>
<h1>Comunicação ao Sistema de Justiça</h1>
<p>{{ $case->municipality->fullName() }} · SIGIEP · protocolo {{ $case->protocol }} · emitido em {{ now()->format('d/m/Y H:i') }}</p>
<table>
@if ($recipient)<tr><td>Destinatário</td><td>{{ $recipient }}</td></tr>@endif
@if ($process)<tr><td>Processo</td><td>{{ $process->number }}{{ $process->court_unit ? ' · '.$process->court_unit : '' }}</td></tr>@endif
<tr><td>Criança/adolescente</td><td>{{ $case->child->name }} · nascimento {{ $case->child->birth_date?->format('d/m/Y') ?? 'não informado' }}</td></tr>
<tr><td>Condição</td><td>{{ $case->condition->label() }}</td></tr>
<tr><td>Violência</td><td>{{ $types }}</td></tr>
<tr><td>Recebimento do caso</td><td>{{ $case->received_at->format('d/m/Y') }} · {{ $case->origin }}</td></tr>
</table>

<h2>Atendimento realizado</h2>
@forelse ($case->sessions->where('status', \App\Enums\SessionStatus::Realizada)->sortBy('scheduled_at') as $s)
<p>Escuta especializada em {{ $s->scheduled_at->format('d/m/Y') }}, por {{ $s->professional->name }}.</p>
@empty<p>Escuta especializada ainda não realizada.</p>@endforelse

<h2>Encaminhamentos da rede de proteção</h2>
<table><tr><th>Destino</th><th>Envio</th><th>Situação</th></tr>
@forelse ($case->referrals as $r)<tr><td>{{ $r->destination->label() }} {{ $r->destination_detail }}</td><td>{{ $r->sent_at?->format('d/m/Y') ?? '—' }}</td><td>{{ $r->status->label() }}</td></tr>
@empty<tr><td colspan="3">Nenhum.</td></tr>@endforelse</table>

<h2>Síntese técnica</h2>
<pre>{{ $summary }}</pre>
<p style="margin-top:10px"><em>Este documento não reproduz o conteúdo da escuta especializada, para evitar a revitimização (art. 5º, VI, e art. 22 da Lei 13.431/2017); o depoimento especial, quando necessário, é colhido em juízo.</em></p>

<div class="sig">_____________________________<br>{{ $author->name }} · {{ $author->role->label() }}{{ $author->registro_profissional ? ' · '.$author->registro_profissional : '' }}</div>
</body></html>
