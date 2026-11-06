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

    <div class="card">
        <h2>Sobre a criança / adolescente</h2>
        <div class="grid">
            <div><label>Nome (se souber)</label><input type="text" name="child[name]" value="{{ old('child.name') }}"></div>
            <div><label>Idade aproximada</label><input type="number" name="child[age]" min="0" max="18" value="{{ old('child.age') }}"></div>
            <div><label>Sexo</label><input type="text" name="child[sex]" value="{{ old('child.sex') }}"></div>
            <div><label>Escola (se souber)</label><input type="text" name="child[school]" value="{{ old('child.school') }}"></div>
            <div><label>Endereço / ponto de referência</label><input type="text" name="child[address]" value="{{ old('child.address') }}"></div>
            <div><label>Bairro</label><input type="text" name="child[neighborhood]" value="{{ old('child.neighborhood') }}"></div>
            <div><label>Nome de um responsável</label><input type="text" name="guardian[name]" value="{{ old('guardian.name') }}"></div>
            <div><label>Telefone do responsável</label><input type="text" name="guardian[phone]" value="{{ old('guardian.phone') }}"></div>
        </div>
    </div>

    <div class="card">
        <h2>O que aconteceu</h2>
        <div class="grid">
            <div><label>A criança é *</label>
                <select name="condition" required>@foreach ($conditions as $c)<option value="{{ $c->value }}" @selected(old('condition') === $c->value)>{{ $c->label() }}</option>@endforeach</select></div>
            <div><label>Quando aconteceu (se souber)</label><input type="date" name="violence_date" value="{{ old('violence_date') }}"></div>
        </div>
        <p><label>Tipo(s) de violência *</label>
            @foreach ($violenceTypes as $t)
                <label class="check"><input type="checkbox" name="violence_types[]" value="{{ $t->value }}" @checked(in_array($t->value, old('violence_types', []), true))> {{ $t->label() }}</label>
            @endforeach
        </p>
        <p><label>Descreva o que você sabe *</label><textarea name="description" required>{{ old('description') }}</textarea></p>
        <div class="check"><input type="hidden" name="urgent" value="0"><input type="checkbox" name="urgent" value="1" @checked(old('urgent'))> <strong>A criança corre risco imediato</strong></div>
    </div>

    @include('casos._ficha', ['ficha' => [], 'showNotifier' => true])

    <button>Enviar denúncia</button>
</form>
@endsection
