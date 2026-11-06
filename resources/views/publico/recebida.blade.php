@extends('layouts.app')
@section('title', 'Denúncia recebida')
@section('content')
<div class="card" style="max-width:640px;margin:30px auto">
    <h1>Denúncia recebida</h1>
    <p>Guarde o número do protocolo: <strong style="font-size:20px">{{ session('protocol') }}</strong></p>
    <p>A equipe de {{ $municipality->fullName() }} fará a triagem. Por sigilo, não é possível consultar o andamento por este canal.</p>
    @if (session('urgent'))
        <div class="errors">Como há risco imediato, <strong>ligue também para o 190</strong>.</div>
    @endif
    <p class="muted">Obrigado por proteger uma criança.</p>
</div>
@endsection
