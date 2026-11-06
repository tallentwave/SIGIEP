<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:10.5px;color:#111} h1{font-size:14px;margin:4px 0} h2{font-size:11px;margin:0}
table{width:100%;border-collapse:collapse} td{padding:3px 5px;border:1px solid #bbb;vertical-align:top} td.n{width:28px;text-align:center;background:#eee;font-weight:bold}
td.t{width:34%;background:#f7f7f7} .box{border:2px solid #b42318;padding:5px;font-weight:bold;margin-bottom:6px} .warn{border:1px solid #b4540a;padding:5px;margin:8px 0}
.sig{margin-top:34px} ul{margin:2px 0 2px 14px;padding:0} .small{font-size:9px;color:#444}
</style></head><body>
<div class="box">DOCUMENTO SIGILOSO · contém dados pessoais de criança/adolescente em situação de violência.</div>
<h1>Ficha de Notificação · Violência Interpessoal/Autoprovocada (para digitação no SINAN)</h1>
<p class="small">Protocolo SIGIEP {{ $case->protocol }} · gerada em {{ now()->format('d/m/Y H:i') }} por {{ $author->name }}. Os números à esquerda são os campos da ficha, na ordem do dicionário de dados SINAN NET v5.1; o valor vem seguido do código a digitar.</p>

<table>
@foreach ($items as $it)
<tr><td class="n">{{ $it['n'] }}</td><td class="t">{{ $it['title'] }}</td>
    <td>@if (isset($it['group']))<ul>@foreach ($it['group'] as $label => $v)<li>{{ $label }}: <strong>{{ $v }}</strong></li>@endforeach</ul>@else{{ $it['value'] }}@endif</td></tr>
@endforeach
</table>

@if (count($warnings))
<div class="warn"><strong>Conferir antes de notificar (preenchido como "Ignorado" por falta de dado):</strong><ul>@foreach ($warnings as $w)<li>{{ $w }}</li>@endforeach</ul></div>
@endif
<p class="small">Campos da ficha oficial não coletados pelo SIGIEP (ex.: Cartão SUS, ocupação, escolaridade, gestante, CEP, telefone, orientação sexual, identidade de gênero, deficiências, motivação, lesão, procedimentos, circunstância da lesão) devem ser completados pela unidade conforme a ficha oficial vigente do Ministério da Saúde.</p>

<div class="sig">______________________________________<br>Profissional notificador: {{ $author->name }} · {{ $author->role->label() }}{{ $author->registro_profissional ? ' · '.$author->registro_profissional : '' }}</div>
</body></html>
