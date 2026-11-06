@extends('layouts.app')
@section('title', 'Estatísticas')
@section('content')
<h1>Estatísticas · {{ $municipalityName }}</h1>
<form class="card row" method="get">
    <div><label>De</label><input type="date" name="de" value="{{ $from->toDateString() }}"></div>
    <div><label>Até</label><input type="date" name="ate" value="{{ $to->toDateString() }}"></div>
    @if ($municipalities->isNotEmpty())
        <div><label>Município</label><select name="municipio"><option value="">Todos</option>@foreach ($municipalities as $m)<option value="{{ $m->id }}" @selected($municipalityId === $m->id)>{{ $m->fullName() }}</option>@endforeach</select></div>
    @endif
    <div><button>Filtrar</button></div>
    <div><a class="btn ghost" href="{{ route('estatisticas.pdf', request()->query()) }}">Baixar PDF</a></div>
</form>
<div class="card">@include('relatorios._tabelas')</div>
<p class="muted">Indicadores agregados: não identificam crianças, famílias ou profissionais.</p>
@endsection
