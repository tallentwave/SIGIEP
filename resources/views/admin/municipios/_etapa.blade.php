<div class="grid">
    <div><label>Nome da etapa *</label><input type="text" name="name" value="{{ $step?->name }}" required placeholder="Ex.: Comunicar Conselho Tutelar"></div>
    <div><label>Destino *</label><select name="destination">@foreach ($destinations as $d)<option value="{{ $d->value }}" @selected($step?->destination === $d)>{{ $d->label() }}</option>@endforeach</select></div>
    <div><label>Unidade / serviço do município</label><input type="text" name="destination_detail" value="{{ $step?->destination_detail }}"></div>
    <div><label>Prazo de retorno (dias)</label><input type="number" name="due_days" min="0" max="365" value="{{ $step?->due_days ?? 5 }}" required></div>
    <div><label>Ordem</label><input type="number" name="position" min="1" max="999" value="{{ $step?->position }}"></div>
</div>
<p><label>Vale para (nenhum marcado = todos os tipos)</label>
    @foreach ($violenceTypes as $t)
        <label class="check" style="display:inline-flex;margin-right:14px"><input type="checkbox" name="violence_types[]" value="{{ $t->value }}" @checked(in_array($t->value, $step?->violence_types ?? [], true))> {{ $t->label() }}</label>
    @endforeach
</p>
<div class="check"><input type="hidden" name="only_urgent" value="0"><input type="checkbox" name="only_urgent" value="1" @checked($step?->only_urgent)> Somente casos urgentes</div>
<div class="check"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($step?->active ?? true)> Etapa ativa</div>
<p></p>
