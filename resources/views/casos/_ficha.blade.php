{{-- Ficha de notificação (estilo SINAN). Espera $ficha (array) e opcional $showNotifier. --}}
@php
    use App\Support\Ficha as F;
    $v = fn ($k, $d = null) => old("ficha.$k", $ficha[$k] ?? $d);
    $select = function (string $field, string $col = '') use ($v) {
        $html = '<div><label>'.e(F::LABELS[$field]).'</label><select name="ficha['.$field.']"><option value="">—</option>';
        foreach (F::options($field) as $val => $lab) {
            $html .= '<option value="'.e($val).'"'.($v($field) === $val ? ' selected' : '').'>'.e($lab).'</option>';
        }
        return new \Illuminate\Support\HtmlString($html.'</select></div>');
    };
@endphp
<div class="card">
    <h2>Dados da ocorrência</h2>
    <div class="grid">
        {{ $select('local_ocorrencia') }}
        {{ $select('zona') }}
        {{ $select('ocorreu_outras_vezes') }}
        {{ $select('raca_cor') }}
    </div>
    <p style="margin-top:12px"><label>Meio de agressão (marque todos que se aplicam)</label>
        <span class="grid">
        @foreach (F::options('meio_agressao') as $val => $lab)
            <label class="check"><input type="checkbox" name="ficha[meio_agressao][]" value="{{ $val }}" @checked(in_array($val, (array) $v('meio_agressao', []), true))> {{ $lab }}</label>
        @endforeach
        </span></p>
    <p><label>Se violência sexual, tipo</label>
        <span class="grid">
        @foreach (F::options('tipo_sexual') as $val => $lab)
            <label class="check"><input type="checkbox" name="ficha[tipo_sexual][]" value="{{ $val }}" @checked(in_array($val, (array) $v('tipo_sexual', []), true))> {{ $lab }}</label>
        @endforeach
        </span></p>
</div>

<div class="card">
    <h2>Provável autor da violência</h2>
    <div class="grid">
        {{ $select('vinculo_autor') }}
        {{ $select('sexo_autor') }}
        {{ $select('faixa_autor') }}
        {{ $select('suspeita_alcool') }}
        {{ $select('num_envolvidos') }}
    </div>
    <p><label>Descrição / nome / endereço, se conhecido</label><textarea name="ficha[autor_descricao]" style="min-height:70px">{{ $v('autor_descricao') }}</textarea></p>
</div>

@if ($showNotifier ?? false)
<div class="card">
    <h2>Quem está informando</h2>
    <div class="check"><input type="hidden" name="ficha[notificante_anonimo]" value="0"><input type="checkbox" id="anon" name="ficha[notificante_anonimo]" value="1" @checked($v('notificante_anonimo'))><label for="anon" style="margin:0;color:inherit">Prefiro não me identificar (denúncia anônima)</label></div>
    <div class="grid" style="margin-top:8px">
        <div><label>Seu nome</label><input type="text" name="ficha[notificante_nome]" value="{{ $v('notificante_nome') }}"></div>
        <div><label>Telefone</label><input type="text" name="ficha[notificante_telefone]" value="{{ $v('notificante_telefone') }}"></div>
        <div><label>E-mail</label><input type="email" name="ficha[notificante_email]" value="{{ $v('notificante_email') }}"></div>
        {{ $select('vinculo_notificante') }}
    </div>
    <p class="muted">Se marcar anônimo, os dados de contato acima serão descartados.</p>
</div>
@endif
