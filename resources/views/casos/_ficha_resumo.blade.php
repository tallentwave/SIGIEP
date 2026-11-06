@php use App\Support\Ficha as F; @endphp
<dl>
    @foreach (['local_ocorrencia','zona','ocorreu_outras_vezes','raca_cor','vinculo_autor','sexo_autor','faixa_autor','suspeita_alcool','num_envolvidos'] as $f)
        @if (! empty($ficha[$f]))<dt>{{ F::LABELS[$f] }}</dt><dd>{{ F::label($f, $ficha[$f]) }}</dd>@endif
    @endforeach
    @foreach (['meio_agressao','tipo_sexual'] as $f)
        @if (! empty($ficha[$f]))<dt>{{ F::LABELS[$f] }}</dt><dd>{{ collect($ficha[$f])->map(fn ($x) => F::label($f, $x))->implode(', ') }}</dd>@endif
    @endforeach
    @if (! empty($ficha['autor_descricao']))<dt>Autor (descrição)</dt><dd>{{ $ficha['autor_descricao'] }}</dd>@endif
    @if (! empty($ficha['notificante_anonimo']))<dt>Notificante</dt><dd>Anônimo</dd>
    @elseif (! empty($ficha['notificante_nome']) || ! empty($ficha['notificante_telefone']) || ! empty($ficha['notificante_email']))
        <dt>Notificante</dt><dd>{{ collect([$ficha['notificante_nome'] ?? null, $ficha['notificante_telefone'] ?? null, $ficha['notificante_email'] ?? null])->filter()->implode(' · ') }}
            @if (! empty($ficha['vinculo_notificante'])) ({{ F::label('vinculo_notificante', $ficha['vinculo_notificante']) }})@endif</dd>
    @endif
</dl>
