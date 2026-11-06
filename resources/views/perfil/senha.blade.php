@extends('layouts.app')
@section('title', 'Alterar senha')
@section('content')
<div class="card" style="max-width:420px;margin:30px auto">
    <h1>Alterar senha</h1>
    <form method="post" action="{{ route('senha.update') }}">@csrf @method('PUT')
        <p><label>Senha atual</label><input type="password" name="current_password" required autocomplete="current-password"></p>
        <p><label>Nova senha (mín. 10, letras e números)</label><input type="password" name="password" required autocomplete="new-password"></p>
        <p><label>Confirmar nova senha</label><input type="password" name="password_confirmation" required autocomplete="new-password"></p>
        <button>Salvar</button>
    </form>
</div>
@endsection
