@extends('layouts.app')
@section('title', 'Denúncia')
@section('content')
<h1>Denunciar violência contra criança ou adolescente</h1>
<div class="errors"><strong>Em perigo agora?</strong> Ligue <strong>190</strong> (Polícia) ou <strong>192</strong> (SAMU). Denúncias também podem ser feitas no <strong>Disque 100</strong> (24h, gratuito, anônimo).</div>
<div class="card">
    <h2>Escolha o município</h2>
    @forelse ($municipalities as $m)
        <p><a href="{{ route('denuncia.create', $m->slug) }}">{{ $m->fullName() }}</a></p>
    @empty
        <p class="muted">Nenhum município recebe denúncias por este canal no momento. Ligue para o Disque 100.</p>
    @endforelse
</div>
@endsection
