@php
    $tbl = function (string $title, array $rows) {
        $max = max(1, max($rows ?: [0]));
        $h = '<h2>'.e($title).'</h2><table>';
        foreach ($rows as $k => $n) {
            $h .= '<tr><td>'.e($k).'</td><td style="width:60px"><strong>'.$n.'</strong></td><td style="width:45%"><div style="background:#1f5f8b;height:8px;width:'.round($n / $max * 100).'%"></div></td></tr>';
        }
        return new \Illuminate\Support\HtmlString($h.($rows ? '' : '<tr><td class="muted">Sem dados no período.</td></tr>').'</table>');
    };
@endphp
<table>
    <tr><td>Casos recebidos</td><td><strong>{{ $total }}</strong></td><td>Urgentes</td><td><strong>{{ $urgent }}</strong></td></tr>
    <tr><td>Via denúncia pública</td><td><strong>{{ $publicas }}</strong></td><td>Sem escuta realizada</td><td><strong>{{ $withoutListening }}</strong></td></tr>
    <tr><td>Tempo médio até a 1ª escuta</td><td><strong>{{ $avgDaysToFirst !== null ? $avgDaysToFirst.' dias' : '—' }}</strong></td>
        <td>Encaminhamentos (retorno / total / atrasados)</td><td><strong>{{ $refReturned }} / {{ $refTotal }} / {{ $refOverdue }}</strong></td></tr>
</table>
{{ $tbl('Por tipo de violência (um caso pode ter mais de um)', $byType) }}
{{ $tbl('Por faixa etária', $byAge) }}
{{ $tbl('Por situação', $byStatus) }}
{{ $tbl('Por mês de recebimento', $byMonth) }}
{{ $tbl('Principais órgãos demandantes', $byOrigin) }}
