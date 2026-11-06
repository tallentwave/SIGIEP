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
<form method="post" action="{{ route('admin.municipios.etapas.destroy', [$municipality, $step]) }}" style="margin:-8px 0 16px" onsubmit="return confirm('Remover esta etapa? Encaminhamentos já gerados são mantidos.')">
    @csrf @method('DELETE') <button class="ghost">Remover etapa acima</button>
</form>
@endforeach

<form class="card" method="post" action="{{ route('admin.municipios.etapas.store', $municipality) }}">
    @csrf
    <h2>Nova etapa</h2>
    @include('admin.municipios._etapa', ['step' => null])
    <button>Adicionar etapa</button>
</form>
@endsection
