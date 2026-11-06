<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#111} h1{font-size:16px} h2{font-size:13px;margin:14px 0 4px}
table{width:100%;border-collapse:collapse} td{padding:3px 4px;border-bottom:1px solid #ddd} .muted{color:#666}
</style></head><body>
<h1>SIGIEP · Relatório estatístico</h1>
<p>{{ $municipalityName }} · período {{ $from->format('d/m/Y') }} a {{ $to->format('d/m/Y') }} · emitido em {{ now()->format('d/m/Y H:i') }}</p>
@include('relatorios._tabelas')
<p class="muted">Indicadores agregados, sem identificação individual.</p>
</body></html>
