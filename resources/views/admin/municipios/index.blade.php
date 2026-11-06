@extends('layouts.app')
@section('title', 'Municípios')
@section('content')
<h1>Municípios <a class="btn" style="float:right" href="{{ route('admin.municipios.create') }}">Novo município</a></h1>
<div class="card"><table>
    <tr><th>Município</th><th>Usuários</th><th>Casos</th><th>Etapas do fluxo</th><th>Canal público</th><th></th></tr>
    @foreach ($municipalities as $m)
        <tr><td>{{ $m->fullName() }} @unless ($m->active)<span class="badge warn">inativo</span>@endunless</td>
            <td>{{ $m->users_count }}</td><td>{{ $m->cases_count }}</td><td>{{ $m->flow_steps_count }}</td>
            <td>@if ($m->accepts_public_reports)<a href="{{ route('denuncia.create', $m->slug) }}">/denuncia/{{ $m->slug }}</a>@else —@endif</td>
            <td><a href="{{ route('admin.municipios.edit', $m) }}">Editar</a> · <a href="{{ route('admin.municipios.fluxo', $m) }}">Fluxo</a></td></tr>
    @endforeach
</table></div>
@endsection
