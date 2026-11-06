@extends('layouts.app')
@php $editing = $case->exists; @endphp
@section('title', $editing ? 'Editar '.$case->protocol : 'Novo caso')
@section('content')
<h1>{{ $editing ? 'Editar caso '.$case->protocol : 'Novo caso' }}</h1>
<form method="post" action="{{ $editing ? route('casos.update', $case) : route('casos.store') }}">
    @csrf @if ($editing) @method('PUT') @endif
    @if ($child->exists && ! $editing)
        <input type="hidden" name="child_id" value="{{ $child->id }}">
        <div class="flash">Novo caso para criança/adolescente já atendido(a): <strong>{{ $child->displayName() }}</strong>. O histórico fica vinculado ao mesmo cadastro.</div>
    @endif

    @include('casos._campos', ['public' => false])

    @if ($canReadSensitive)
        @include('casos._ficha', ['ficha' => $ficha])
    @else
        <div class="card muted">Seu perfil não acessa a ficha de notificação nem o relato inicial deste caso.</div>
    @endif

    <button>{{ $editing ? 'Salvar alterações' : 'Cadastrar caso' }}</button>
    <a class="btn ghost" href="{{ $editing ? route('casos.show', $case) : route('casos.index') }}">Cancelar</a>
</form>
@endsection
