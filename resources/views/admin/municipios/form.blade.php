@extends('layouts.app')
@section('title', 'Município')
@section('content')
<h1>{{ $municipality->exists ? 'Editar município' : 'Novo município' }}</h1>
<form class="card" method="post" action="{{ $municipality->exists ? route('admin.municipios.update', $municipality) : route('admin.municipios.store') }}">
    @csrf @if ($municipality->exists) @method('PUT') @endif
    <div class="grid">
        <div><label>Nome *</label><input type="text" name="name" value="{{ old('name', $municipality->name) }}" required></div>
        <div><label>UF *</label><input type="text" name="uf" maxlength="2" value="{{ old('uf', $municipality->uf) }}" required></div>
        <div><label>Código IBGE (7 dígitos)</label><input type="text" name="ibge_code" value="{{ old('ibge_code', $municipality->ibge_code) }}"></div>
        <div><label>Telefone exibido no formulário público</label><input type="text" name="contact_phone" value="{{ old('contact_phone', $municipality->contact_phone) }}"></div>
    </div>
    <div class="check"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked(old('active', $municipality->active))> Município ativo</div>
    <div class="check"><input type="hidden" name="accepts_public_reports" value="0"><input type="checkbox" name="accepts_public_reports" value="1" @checked(old('accepts_public_reports', $municipality->accepts_public_reports))> Recebe denúncias pelo formulário público</div>
    <p><button>Salvar</button> <a class="btn ghost" href="{{ route('admin.municipios.index') }}">Cancelar</a></p>
</form>
@endsection
