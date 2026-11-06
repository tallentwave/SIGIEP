@php use App\Support\Ficha as F; @endphp
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#111} h1{font-size:16px} h2{font-size:13px;margin:14px 0 4px;border-bottom:1px solid #999}
table{width:100%;border-collapse:collapse} td,th{padding:3px 4px;border-bottom:1px solid #ddd;text-align:left;vertical-align:top} .box{border:2px solid #b42318;padding:6px;font-weight:bold;margin-bottom:8px}
pre{white-space:pre-wrap;font-family:inherit;margin:0}
</style></head><body>
<div class="box">DOCUMENTO SIGILOSO · Lei 13.431/2017 · uso restrito à rede de proteção. Emitido por {{ $generatedBy->name }} em {{ now()->format('d/m/Y H:i') }}.</div>
<h1>Relatório do caso {{ $case->protocol }} · {{ $case->municipality->fullName() }}</h1>

<h2>Identificação</h2>
<table>
<tr><td>Nome</td><td>{{ $case->child->name }}</td><td>Nascimento</td><td>{{ $case->child->birth_date?->format('d/m/Y') ?? '—' }}</td></tr>
<tr><td>Mãe</td><td>{{ $case->child->mother_name ?? '—' }}</td><td>Endereço</td><td>{{ collect([$case->child->address, $case->child->neighborhood, $case->child->city])->filter()->implode(', ') ?: '—' }}</td></tr>
</table>
@if ($case->child->guardians->isNotEmpty())
<h2>Responsáveis</h2>
<table>@foreach ($case->child->guardians as $g)
<tr><td>{{ $g->name }}</td><td>{{ $g->relationship }}</td><td>{{ $g->phone }}</td><td>{{ $g->is_suspected ? 'SUPOSTO AUTOR' : '' }}{{ $g->can_be_contacted ? '' : ' · NÃO CONTATAR' }}</td></tr>
@endforeach</table>
@endif

<h2>Caso</h2>
<table>
<tr><td>Condição</td><td>{{ $case->condition->label() }}</td><td>Recebido</td><td>{{ $case->received_at->format('d/m/Y') }}</td></tr>
<tr><td>Violência</td><td colspan="3">{{ collect($case->violence_types)->map(fn ($v) => \App\Enums\ViolenceType::from($v)->label())->implode(', ') }}</td></tr>
<tr><td>Órgão demandante</td><td colspan="3">{{ $case->origin }} {{ $case->origin_document }}</td></tr>
<tr><td>Responsável</td><td>{{ $case->responsible?->name ?? '—' }}</td><td>Situação</td><td>{{ $case->status->label() }}</td></tr>
</table>
@if ($case->ficha)<h2>Ficha de notificação</h2>@include('casos._ficha_resumo', ['ficha' => $case->ficha])@endif
@if ($case->initial_report)<h2>Relato inicial</h2><pre>{{ $case->initial_report }}</pre>@endif

<h2>Escutas especializadas</h2>
@forelse ($case->sessions->sortBy('scheduled_at') as $s)
<p><strong>{{ $s->scheduled_at->format('d/m/Y H:i') }}</strong> · {{ $s->professional->name }} · {{ $s->status->label() }}{{ $s->room ? ' · '.$s->room : '' }}</p>
@if ($s->report)<pre>{{ $s->report }}</pre>@endif
@if ($s->technical_notes)<p><em>Observações técnicas:</em></p><pre>{{ $s->technical_notes }}</pre>@endif
@empty<p>Nenhuma escuta registrada.</p>@endforelse

<h2>Encaminhamentos</h2>
<table><tr><th>Destino</th><th>Envio</th><th>Prazo</th><th>Situação</th><th>Retorno</th></tr>
@forelse ($case->referrals as $r)
<tr><td>{{ $r->destination->label() }} {{ $r->destination_detail }}</td><td>{{ $r->sent_at?->format('d/m/Y') ?? '—' }}</td><td>{{ $r->due_at?->format('d/m/Y') ?? '—' }}</td><td>{{ $r->status->label() }}</td><td>{{ $r->returned_at?->format('d/m/Y') ?? '—' }}</td></tr>
@empty<tr><td colspan="5">Nenhum.</td></tr>@endforelse</table>
</body></html>
