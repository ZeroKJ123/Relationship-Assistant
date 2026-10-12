@extends('layouts.admin')
@section('title', __('Иконки'))
@section('admin-content')
<h1 class="screen-title">{{ __('Иконки') }}</h1><p>{{ __('Замените иллюстрацию эмоджи, символом или своей картинкой. Замена действует во всех местах использования, включая нижнее меню. «Из темы» возвращает оформление выбранной темы.') }}</p>
<div class="theme-grid section-space">@foreach(\App\Services\Appearance\AppearanceCatalog::ICONS as $icon)
<x-card><div class="icon-editor-preview"><x-illustration :name="$icon" /><strong>{{ $catalog->iconLabels()[$icon] }}</strong></div>
<form method="post" action="{{ route('admin.panel.appearance.update') }}" enctype="multipart/form-data" class="form-stack" data-submit-lock>@csrf @method('put')
<input type="hidden" name="section" value="icons"><input type="hidden" name="revision" value="{{ $revision }}"><input type="hidden" name="icon" value="{{ $icon }}">
<x-select-field name="kind" :label="__('Вид иконки')"><option value="default">{{ __('Из темы / обычная') }}</option><option value="glyph" @selected(($settings['icons'][$icon]['kind'] ?? '') === 'glyph')>{{ __('Эмоджи или символ') }}</option><option value="image" @selected(($settings['icons'][$icon]['kind'] ?? '') === 'image')>{{ __('Своя картинка') }}</option></x-select-field>
<x-field :id="'glyph-'.$icon" name="glyph" :label="__('Символ')" :value="($settings['icons'][$icon]['kind'] ?? '') === 'glyph' ? $settings['icons'][$icon]['value'] : ''" maxlength="12" />
<x-field :id="'image-'.$icon" name="image" type="file" :label="__('Картинка до 4 МБ')" accept="image/png,image/jpeg,image/webp" />
<label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Применить ко всем пользователям') }}</label><x-button type="submit">{{ __('Сохранить иконку') }}</x-button>
</form></x-card>@endforeach</div>
@endsection
