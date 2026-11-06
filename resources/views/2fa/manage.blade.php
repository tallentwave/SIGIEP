@extends('layouts.app')
@section('title', 'Segurança da conta')
@section('content')
<h1>Segurança da conta</h1>
@if ($codes)
    <div class="card sigilo">
        <h2>Códigos de recuperação</h2>
        <p>Guarde em local seguro (gerenciador de senhas). Cada código vale uma vez e <strong>não será mostrado novamente</strong>.</p>
        <pre style="font-size:16px">{{ implode("\n", $codes) }}</pre>
    </div>
@endif
<div class="card">
    <h2>Verificação em duas etapas</h2>
    @if ($user->hasTwoFactor())
        <p><span class="badge">ativa</span> desde {{ $user->two_factor_confirmed_at->format('d/m/Y') }} · {{ count($user->two_factor_recovery_codes ?? []) }} código(s) de recuperação restantes.</p>
        <form method="post" action="{{ route('2fa.regenerate') }}" class="row">@csrf
            <div><label>Senha atual</label><input type="password" name="password" required></div>
            <div><button class="ghost">Gerar novos códigos de recuperação</button></div>
        </form>
        @unless ($user->requiresTwoFactor())
            <form method="post" action="{{ route('2fa.disable') }}" class="row" style="margin-top:12px">@csrf @method('DELETE')
                <div><label>Senha atual</label><input type="password" name="password" required></div>
                <div><button class="ghost">Desativar 2FA</button></div>
            </form>
        @else
            <p class="muted">Seu perfil exige 2FA; não é possível desativar.</p>
        @endunless
    @else
        <p><span class="badge warn">desativada</span> {{ $user->requiresTwoFactor() ? 'Obrigatória para o seu perfil.' : 'Recomendada.' }}</p>
        <a class="btn" href="{{ route('2fa.setup') }}">Configurar agora</a>
    @endif
</div>
<div class="card"><h2>Senha</h2><a class="btn ghost" href="{{ route('senha.edit') }}">Alterar senha</a></div>
@endsection
