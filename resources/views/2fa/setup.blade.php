@extends('layouts.app')
@section('title', 'Configurar 2FA')
@section('content')
<div class="card" style="max-width:520px;margin:30px auto">
    <h1>Verificação em duas etapas</h1>
    <p>1. Instale um aplicativo autenticador (Google Authenticator, Microsoft Authenticator, Authy, 1Password…).<br>
       2. Escaneie o QR code (ou digite a chave).<br>
       3. Digite abaixo o código de 6 dígitos que o aplicativo mostrar.</p>
    <div style="background:#fff;display:inline-block;padding:8px;border-radius:6px">{!! $qr !!}</div>
    <p class="muted">Chave manual: <code style="user-select:all">{{ $secret }}</code></p>
    <form method="post" action="{{ route('2fa.confirm') }}">@csrf
        <p><label>Código de 6 dígitos</label><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus></p>
        <button>Ativar</button>
    </form>
    <form method="post" action="{{ route('logout') }}" style="margin-top:12px">@csrf <button class="ghost">Sair</button></form>
</div>
@endsection
