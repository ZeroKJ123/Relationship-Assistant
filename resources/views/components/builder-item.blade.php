@props(['item', 'index', 'group', 'catalog'])
<article class="builder-item card" data-builder-item data-item-id="{{ $item['id'] }}" data-item-type="{{ $item['type'] ?? $item['id'] }}">
<div class="section-heading"><button type="button" class="button button--quiet builder-handle" data-drag-handle aria-label="{{ __('Перетащить блок') }}">⠿</button><strong>{{ $group === 'blocks' ? $catalog->blockLabels()[$item['type']] : $catalog->tileLabels()[$item['id']] }}</strong><div class="builder-move"><button type="button" class="button button--quiet" data-move="up" aria-label="{{ __('Выше') }}">↑</button><button type="button" class="button button--quiet" data-move="down" aria-label="{{ __('Ниже') }}">↓</button></div></div>
<input type="hidden" name="layout[{{ $group }}][{{ $index }}][id]" value="{{ $item['id'] }}" data-prop="id">
@if($group === 'blocks')<input type="hidden" name="layout[blocks][{{ $index }}][type]" value="{{ $item['type'] }}" data-prop="type">@endif
<div class="builder-item-controls">
<label class="confirmation-check"><input type="hidden" name="layout[{{ $group }}][{{ $index }}][enabled]" value="0"><input type="checkbox" name="layout[{{ $group }}][{{ $index }}][enabled]" value="1" data-prop="enabled" @checked($item['enabled'])>{{ __('Показывать') }}</label>
<x-select-field :name="'layout['.$group.']['.$index.'][condition]'" :label="__('Условие показа')" data-prop="condition">@foreach($catalog->conditionLabels() as $condition=>$label)<option value="{{ $condition }}" @selected($item['condition'] === $condition)>{{ $label }}</option>@endforeach</x-select-field>
<x-select-field :name="'layout['.$group.']['.$index.'][tone]'" :label="__('Цвет карточки')" data-prop="tone">@foreach(['plain'=>__('Белый'),'yellow'=>__('Жёлтый'),'purple'=>__('Основной'),'pink'=>__('Розовый')] as $tone=>$label)<option value="{{ $tone }}" @selected($item['tone'] === $tone)>{{ $label }}</option>@endforeach</x-select-field>
<x-field :name="'layout['.$group.']['.$index.'][title]'" :label="__('Своя подпись (необязательно)')" :value="$item['title']" maxlength="100" data-prop="title" />
@if($group === 'blocks')@if($item['type'] === 'text')<x-textarea-field :name="'layout[blocks]['.$index.'][text]'" :label="__('Текст')" :value="$item['text']" maxlength="2000" data-prop="text" /><button type="button" class="button button--quiet" data-remove-text>{{ __('Удалить карточку') }}</button>@else<input type="hidden" name="layout[blocks][{{ $index }}][text]" value="" data-prop="text">@endif @endif
</div>
</article>
