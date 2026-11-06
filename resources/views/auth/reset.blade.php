@extends('layouts.app')
@section('title', 'Redefinir senha')
@section('content')
<div class="card" style="max-width:420px;margin:60px auto">
    <h1>Nova senha</h1>
    <form method="post" action="{{ route('password.update') }}">@csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <p><label>E-mail</label><input type="email" name="email" value="{{ old('email', $email) }}" required></p>
        <p><label>Nova senha (mín. 10, letras e números)</label><input type="password" name="password" required autocomplete="new-password"></p>
        <p><label>Confirmar nova senha</label><input type="password" name="password_confirmation" required autocomplete="new-password"></p>
        <button>Redefinir</button>
    </form>
</div>
@endsection
