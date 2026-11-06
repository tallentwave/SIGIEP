@extends('layouts.app')
@section('title', 'Verificação em duas etapas')
@section('content')
<div class="card" style="max-width:420px;margin:60px auto">
    <h1>Verificação em duas etapas</h1>
    <form method="post" action="{{ route('2fa.verify') }}">@csrf
        <p><label>Código do aplicativo autenticador</label><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" autofocus></p>
        <button>Verificar</button>
    </form>
    <details style="margin-top:16px"><summary class="muted">Perdi o celular: usar código de recuperação</summary>
        <form method="post" action="{{ route('2fa.verify') }}" style="margin-top:8px">@csrf
            <p><label>Código de recuperação</label><input type="text" name="recovery_code" autocomplete="off" placeholder="xxxxx-xxxxx"></p>
            <button class="ghost">Usar código</button>
        </form>
        <p class="muted">Sem os códigos? Peça ao administrador para redefinir o seu 2FA.</p>
    </details>
    <form method="post" action="{{ route('logout') }}" style="margin-top:12px">@csrf <button class="ghost">Sair</button></form>
</div>
@endsection
