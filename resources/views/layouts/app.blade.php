<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'SIGIEP') · Escuta Especializada</title>
    <style>
        :root { --bg:#f5f6f8; --card:#fff; --ink:#1d2430; --muted:#657080; --line:#dde1e7; --brand:#1f5f8b; --brand-d:#174866; --warn:#b4540a; --bad:#b42318; --ok:#157347; }
        @media (prefers-color-scheme: dark) { :root { --bg:#14181f; --card:#1c222c; --ink:#e7eaf0; --muted:#9aa4b2; --line:#2e3644; --brand:#5aa6d6; --brand-d:#82bde3; --warn:#f0a35e; --bad:#f08178; --ok:#5ec48f; } }
        * { box-sizing: border-box; }
        body { margin:0; font:15px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif; background:var(--bg); color:var(--ink); }
        a { color:var(--brand); }
        header.top { background:var(--card); border-bottom:1px solid var(--line); padding:10px 16px; display:flex; gap:16px; align-items:center; flex-wrap:wrap; }
        header.top strong { font-size:17px; }
        header.top nav { display:flex; gap:14px; flex:1; flex-wrap:wrap; }
        header.top .who { color:var(--muted); font-size:13px; }
        main { max-width:1100px; margin:0 auto; padding:20px 16px 60px; }
        h1 { font-size:22px; margin:0 0 16px; } h2 { font-size:17px; margin:0 0 12px; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:8px; padding:16px; margin-bottom:16px; }
        .grid { display:grid; gap:12px; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); }
        .grid.wide { grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); }
        .stat b { display:block; font-size:28px; }
        label { display:block; font-size:13px; color:var(--muted); margin-bottom:3px; }
        input[type=text],input[type=email],input[type=password],input[type=date],input[type=datetime-local],input[type=number],select,textarea { width:100%; padding:8px 10px; border:1px solid var(--line); border-radius:6px; background:var(--bg); color:var(--ink); font:inherit; }
        textarea { min-height:110px; resize:vertical; }
        .check { display:flex; gap:8px; align-items:center; font-size:14px; color:var(--ink); margin:4px 0; } .check input { width:auto; }
        button,.btn { display:inline-block; background:var(--brand); color:#fff; border:0; border-radius:6px; padding:8px 14px; font:inherit; cursor:pointer; text-decoration:none; }
        button.ghost,.btn.ghost { background:transparent; color:var(--brand); border:1px solid var(--line); }
        table { width:100%; border-collapse:collapse; } th,td { text-align:left; padding:8px 6px; border-bottom:1px solid var(--line); vertical-align:top; font-size:14px; } th { color:var(--muted); font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:.03em; }
        .badge { display:inline-block; padding:1px 8px; border-radius:99px; font-size:12px; border:1px solid var(--line); color:var(--muted); }
        .badge.urgent { color:var(--bad); border-color:var(--bad); font-weight:600; } .badge.warn { color:var(--warn); border-color:var(--warn); }
        .flash { padding:10px 14px; border-radius:6px; margin-bottom:16px; border:1px solid var(--ok); color:var(--ok); }
        .errors { padding:10px 14px; border-radius:6px; margin-bottom:16px; border:1px solid var(--bad); color:var(--bad); } .errors ul { margin:0; padding-left:18px; }
        .muted { color:var(--muted); } .sigilo { border-left:4px solid var(--bad); padding-left:12px; }
        .row { display:flex; gap:12px; flex-wrap:wrap; align-items:end; } .row > * { flex:1 1 160px; }
        dl { display:grid; grid-template-columns:max-content 1fr; gap:4px 14px; margin:0; } dt { color:var(--muted); } dd { margin:0; }
        pre.report { white-space:pre-wrap; font:inherit; margin:0; }
    </style>
</head>
<body>
@auth
<header class="top">
    <strong>SIGIEP</strong>
    <nav>
        @unless (auth()->user()->isAdmin())
            <a href="{{ route('dashboard') }}">Painel</a>
            <a href="{{ route('casos.index') }}">Casos</a>
            <a href="{{ route('casos.create') }}">Novo caso</a>
            <a href="{{ route('atendidos.index') }}">Atendidos</a>
        @endunless
        @can('viewStats') <a href="{{ route('estatisticas.index') }}">Estatísticas</a> @endcan
        @if (auth()->user()->isCoordination())<a href="{{ route('integracoes.index') }}">Integrações</a>@endif
        @can('viewRetention') <a href="{{ route('retencao.index') }}">Retenção</a> @endcan
        @can('viewAudit') <a href="{{ route('auditoria.index') }}">Auditoria</a> @endcan
        @if (auth()->user()->isCoordination())<a href="{{ route('admin.municipios.fluxo', auth()->user()->municipality_id) }}">Fluxo municipal</a> <a href="{{ route('admin.municipios.prazos', auth()->user()->municipality_id) }}">Prazos</a>@endif
        @can('admin') <a href="{{ route('admin.usuarios.index') }}">Usuários</a> <a href="{{ route('admin.municipios.index') }}">Municípios</a> @endcan
    </nav>
    <span class="who"><a href="{{ route('2fa.manage') }}" title="Segurança da conta">{{ auth()->user()->name }}</a> · {{ auth()->user()->role->label() }}{{ auth()->user()->municipality ? ' · '.auth()->user()->municipality->name : '' }}</span>
    <form method="post" action="{{ route('logout') }}">@csrf <button class="ghost">Sair</button></form>
</header>
@endauth
<main>
    @if (session('ok')) <div class="flash">{{ session('ok') }}</div> @endif
    @if ($errors->any())
        <div class="errors"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    @yield('content')
</main>
</body>
</html>
