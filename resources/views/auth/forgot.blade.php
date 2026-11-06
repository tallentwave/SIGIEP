@extends('layouts.app')
@section('title', 'Esqueci minha senha')
@section('content')
<div class="card" style="max-width:420px;margin:60px auto">
    <h1>Esqueci minha senha</h1>
    <p class="muted">Informe o e-mail da sua conta. Se ele estiver cadastrado, enviaremos um link para criar uma nova senha.</p>
    <form method="post" action="{{ route('password.email') }}">@csrf
        <p><label>E-mail</label><input type="email" name="email" value="{{ old('email') }}" required autofocus></p>
        <button>Enviar link</button> <a href="{{ route('login') }}" style="margin-left:10px">Voltar</a>
    </form>
</div>
@endsection
