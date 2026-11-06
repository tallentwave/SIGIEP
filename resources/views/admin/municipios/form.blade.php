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
    <h2 style="margin-top:16px">Retenção de dados (LGPD)</h2>
    <div class="grid">
        <div><label>Anos de guarda após o encerramento do caso *</label><input type="number" name="retention_years" min="1" max="50" value="{{ old('retention_years', $municipality->retention_years ?? 10) }}" required>
            <span class="muted">Padrão provisório: valide com a assessoria jurídica / DPO.</span></div>
    </div>
    <h2 style="margin-top:16px">Unidade notificadora (SINAN)</h2>
    <div class="grid">
        <div><label>Tipo da unidade *</label><select name="sinan_unit_type">
            @foreach ([1 => 'Unidade de Saúde', 2 => 'Unidade de Assistência Social', 3 => 'Estabelecimento de Ensino', 4 => 'Conselho Tutelar', 5 => 'Unidade de Saúde Indígena', 6 => 'Centro Esp. de Atendimento à Mulher', 7 => 'Outros'] as $v => $l)
                <option value="{{ $v }}" @selected((int) old('sinan_unit_type', $municipality->sinan_unit_type ?? 7) === $v)>{{ $v }} – {{ $l }}</option>@endforeach</select></div>
        <div><label>Nome da unidade</label><input type="text" name="sinan_unit_name" value="{{ old('sinan_unit_name', $municipality->sinan_unit_name) }}"></div>
        <div><label>Código da unidade (CNES / SUAS / MEC…)</label><input type="text" name="sinan_unit_code" value="{{ old('sinan_unit_code', $municipality->sinan_unit_code) }}"></div>
    </div>
    <div class="check" style="margin-top:12px"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked(old('active', $municipality->active))> Município ativo</div>
    <div class="check"><input type="hidden" name="accepts_public_reports" value="0"><input type="checkbox" name="accepts_public_reports" value="1" @checked(old('accepts_public_reports', $municipality->accepts_public_reports))> Recebe denúncias pelo formulário público</div>
    <p><button>Salvar</button> <a class="btn ghost" href="{{ route('admin.municipios.index') }}">Cancelar</a></p>
</form>
@endsection
