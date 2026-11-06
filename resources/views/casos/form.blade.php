@extends('layouts.app')
@php $editing = $case->exists; @endphp
@section('title', $editing ? 'Editar '.$case->protocol : 'Novo caso')
@section('content')
<h1>{{ $editing ? 'Editar caso '.$case->protocol : 'Novo caso' }}</h1>
<form method="post" action="{{ $editing ? route('casos.update', $case) : route('casos.store') }}">
    @csrf @if ($editing) @method('PUT') @endif
    @if ($child->exists && ! $editing)
        <input type="hidden" name="child_id" value="{{ $child->id }}">
        <div class="flash">Novo caso para criança/adolescente já atendido(a): <strong>{{ $child->displayName() }}</strong>. O histórico fica vinculado ao mesmo cadastro.</div>
    @endif

    <div class="card">
        <h2>Criança / adolescente</h2>
        <div class="grid">
            <div><label>Nome completo *</label><input type="text" name="child[name]" value="{{ old('child.name', $child->name) }}" required></div>
            <div><label>Nome social</label><input type="text" name="child[social_name]" value="{{ old('child.social_name', $child->social_name) }}"></div>
            <div><label>Data de nascimento</label><input type="date" name="child[birth_date]" value="{{ old('child.birth_date', $child->birth_date?->toDateString()) }}">
                <div class="check"><input type="hidden" name="child[birth_date_estimated]" value="0"><input type="checkbox" name="child[birth_date_estimated]" value="1" @checked(old('child.birth_date_estimated', $child->birth_date_estimated))> data estimada</div></div>
            <div><label>Sexo</label><input type="text" name="child[sex]" value="{{ old('child.sex', $child->sex) }}"></div>
            <div><label>CPF</label><input type="text" name="child[cpf]" value="{{ old('child.cpf', $child->cpf) }}"></div>
            <div><label>Nome da mãe</label><input type="text" name="child[mother_name]" value="{{ old('child.mother_name', $child->mother_name) }}"></div>
            <div><label>Escola</label><input type="text" name="child[school]" value="{{ old('child.school', $child->school) }}"></div>
            <div><label>Endereço</label><input type="text" name="child[address]" value="{{ old('child.address', $child->address) }}"></div>
            <div><label>Bairro</label><input type="text" name="child[neighborhood]" value="{{ old('child.neighborhood', $child->neighborhood) }}"></div>
            <div><label>Cidade</label><input type="text" name="child[city]" value="{{ old('child.city', $child->city) }}"></div>
            <div>
                <div class="check"><input type="hidden" name="child[has_disability]" value="0"><input type="checkbox" name="child[has_disability]" value="1" @checked(old('child.has_disability', $child->has_disability))> Possui deficiência</div>
                <label>Observação (acessibilidade)</label><input type="text" name="child[disability_notes]" value="{{ old('child.disability_notes', $child->disability_notes) }}">
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Responsáveis / familiares</h2>
        <p class="muted">Marque "suposto autor" para que a equipe NÃO contate essa pessoa no fluxo de agendamento.</p>
        @php $gs = old('guardians') ? collect(old('guardians')) : $guardians->map->toArray(); @endphp
        @foreach ($gs->pad(max(3, $gs->count()), []) as $i => $g)
            <div class="row" style="margin-bottom:10px">
                <div><label>Nome</label><input type="text" name="guardians[{{ $i }}][name]" value="{{ $g['name'] ?? '' }}"></div>
                <div><label>Parentesco</label><input type="text" name="guardians[{{ $i }}][relationship]" value="{{ $g['relationship'] ?? '' }}"></div>
                <div><label>Telefone</label><input type="text" name="guardians[{{ $i }}][phone]" value="{{ $g['phone'] ?? '' }}"></div>
                <div><label>CPF</label><input type="text" name="guardians[{{ $i }}][cpf]" value="{{ $g['cpf'] ?? '' }}"></div>
                <div>
                    <div class="check"><input type="hidden" name="guardians[{{ $i }}][is_suspected]" value="0"><input type="checkbox" name="guardians[{{ $i }}][is_suspected]" value="1" @checked($g['is_suspected'] ?? false)> suposto autor</div>
                    <div class="check"><input type="hidden" name="guardians[{{ $i }}][can_be_contacted]" value="0"><input type="checkbox" name="guardians[{{ $i }}][can_be_contacted]" value="1" @checked($g['can_be_contacted'] ?? true)> pode ser contatado</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <h2>Caso</h2>
        <div class="grid">
            <div><label>Condição *</label>
                <select name="case[condition]" required>
                    @foreach ($conditions as $c)<option value="{{ $c->value }}" @selected(old('case.condition', $case->condition?->value) === $c->value)>{{ $c->label() }}</option>@endforeach
                </select></div>
            <div><label>Órgão demandante *</label><input type="text" name="case[origin]" value="{{ old('case.origin', $case->origin) }}" placeholder="Conselho Tutelar, Vara da Infância, Delegacia…" required></div>
            <div><label>Nº do ofício / BO / processo</label><input type="text" name="case[origin_document]" value="{{ old('case.origin_document', $case->origin_document) }}"></div>
            <div><label>Data de recebimento *</label><input type="date" name="case[received_at]" value="{{ old('case.received_at', ($case->received_at ?? now())->toDateString()) }}" required></div>
            <div><label>Data do fato</label><input type="date" name="case[violence_date]" value="{{ old('case.violence_date', $case->violence_date?->toDateString()) }}"></div>
            <div><label>Suposto autor</label><input type="text" name="case[alleged_author]" value="{{ old('case.alleged_author', $case->alleged_author) }}"></div>
        </div>
        <p><label>Tipo(s) de violência *</label>
            @foreach ($violenceTypes as $t)
                <label class="check"><input type="checkbox" name="case[violence_types][]" value="{{ $t->value }}" @checked(in_array($t->value, old('case.violence_types', $case->violence_types ?? []), true))> {{ $t->label() }}</label>
            @endforeach
        </p>
        <div class="check"><input type="hidden" name="case[urgent]" value="0"><input type="checkbox" name="case[urgent]" value="1" @checked(old('case.urgent', $case->urgent))> <strong>Caso urgente</strong> (risco iminente)</div>
        @if ($canReadSensitive)
        <p><label>Relato inicial do órgão demandante (sigiloso, armazenado criptografado)</label>
            <textarea name="case[initial_report]">{{ old('case.initial_report', $case->initial_report) }}</textarea>
        </p>
        @endif
        <p class="muted">Lei 13.431/2017: registre apenas o necessário. A criança não deve ser questionada sobre o fato antes da escuta especializada; evite revitimização.</p>
    </div>

    @if ($canReadSensitive)
        @include('casos._ficha', ['ficha' => $ficha])
    @else
        <div class="card muted">Seu perfil não acessa a ficha de notificação nem o relato inicial deste caso.</div>
    @endif

    <button>{{ $editing ? 'Salvar alterações' : 'Cadastrar caso' }}</button>
    <a class="btn ghost" href="{{ $editing ? route('casos.show', $case) : route('casos.index') }}">Cancelar</a>
</form>
@endsection
