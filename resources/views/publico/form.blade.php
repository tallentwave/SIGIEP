@extends('layouts.app')
@section('title', 'Denúncia · '.$municipality->name)
@section('content')
<h1>Denúncia · {{ $municipality->fullName() }}</h1>
<div class="errors"><strong>Em perigo agora?</strong> Ligue <strong>190</strong> ou <strong>192</strong>. Disque 100 (24h, anônimo).
    @if ($municipality->contact_phone) Contato local: {{ $municipality->contact_phone }}.@endif</div>
<p class="muted">Conte o que sabe. Você não precisa ter todas as informações nem provas. Não converse com a criança sobre os detalhes do fato: a equipe especializada fará isso de forma protegida.</p>

<form method="post" action="{{ route('denuncia.store', $municipality->slug) }}">
    @csrf
    <div style="position:absolute;left:-9999px" aria-hidden="true"><label>Não preencha<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

    @include('casos._campos', ['public' => true])

    @include('casos._ficha', ['ficha' => [], 'showNotifier' => true])

    <button>Enviar denúncia</button>
</form>
@endsection
