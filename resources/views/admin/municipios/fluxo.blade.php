@extends('layouts.app')
@section('title', 'Fluxo · '.$municipality->name)
@section('content')
<h1>Fluxo de atendimento · {{ $municipality->fullName() }}</h1>
<p class="muted">Cada etapa ativa vira um encaminhamento pendente, com prazo de retorno, quando um caso é cadastrado ou recebido por denúncia. Etapas podem valer só para certos tipos de violência ou só para casos urgentes.</p>


@foreach ($municipality->flowSteps as $step)
<form class="card" method="post" action="{{ route('admin.municipios.etapas.update', [$municipality, $step]) }}">
    @csrf @method('PUT')
    @include('admin.municipios._etapa', ['step' => $step])
    <button>Salvar etapa</button>
</form>
<details style="margin:-8px 0 16px"><summary class="muted" style="cursor:pointer">Remover esta etapa…</summary>
    <form method="post" action="{{ route('admin.municipios.etapas.destroy', [$municipality, $step]) }}" style="margin-top:6px">
        @csrf @method('DELETE')
        <span class="muted">Encaminhamentos já gerados por ela são mantidos.</span> <button class="ghost">Confirmar remoção</button>
    </form>
</details>
@endforeach

<form class="card" method="post" action="{{ route('admin.municipios.etapas.store', $municipality) }}">
    @csrf
    <h2>Nova etapa</h2>
    @include('admin.municipios._etapa', ['step' => null])
    <button>Adicionar etapa</button>
</form>
@endsection
