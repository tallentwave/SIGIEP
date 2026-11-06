@extends('layouts.app')
@section('title', 'Atendidos')
@section('content')
<h1>Atendidos</h1>
<form class="card row" method="get"><div><label>Nome, nome da mãe ou CPF completo (mín. 3 letras)</label><input type="text" name="q" value="{{ request('q') }}" autofocus></div><div><button>Buscar</button></div></form>
<div class="card">
    @forelse ($children as $c)
        <p><a href="{{ route('atendidos.show', $c) }}">{{ $c->displayName() }}</a> <span class="muted">{{ $c->birth_date?->format('d/m/Y') }} · mãe: {{ $c->mother_name ?? '—' }}</span></p>
    @empty
        <p class="muted">{{ request('q') ? 'Nada encontrado entre os casos que você pode acessar.' : 'Busque para evitar cadastro duplicado antes de abrir um novo caso.' }}</p>
    @endforelse
</div>
@endsection
