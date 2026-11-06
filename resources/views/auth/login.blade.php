@extends('layouts.app')
@section('title', 'Entrar')
@section('content')
<div class="card" style="max-width:380px;margin:60px auto">
    <h1>SIGIEP</h1>
    <p class="muted">Sistema de Escuta Especializada. Acesso restrito a profissionais autorizados; todo acesso é registrado.</p>
    <form method="post" action="{{ route('login') }}">
        @csrf
        <p><label for="email">E-mail</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus></p>
        <p><label for="password">Senha</label><input id="password" type="password" name="password" required></p>
        <p class="check"><input id="remember" type="checkbox" name="remember" value="1"><label for="remember" style="margin:0">Manter conectado</label></p>
        <button>Entrar</button> <a href="{{ route('password.request') }}" style="margin-left:10px">Esqueci minha senha</a>
    </form>
    <p class="muted" style="margin-top:16px">Quer denunciar uma violência? <a href="{{ route('denuncia.index') }}">Acesse o canal de denúncia</a>.</p>
</div>
@endsection
