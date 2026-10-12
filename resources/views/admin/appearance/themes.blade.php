@extends('layouts.admin')
@section('title', __('Темы'))
@section('admin-content')
<h1 class="screen-title">{{ __('Темы') }}</h1><p>{{ __('Общее оформление сайта. Сейчас используется: :theme', ['theme'=>$settings['themes'][$activeId]['name']]) }}</p>
<div class="theme-grid section-space">
@foreach($settings['themes'] as $id=>$theme)
<a class="card theme-swatch" href="{{ route('admin.panel.themes', ['edit'=>$id]) }}" style="--swatch-start:{{ $theme['background'] }};--swatch-end:{{ $theme['gradient'] }};--swatch-accent:{{ $theme['primary'] }}"><span class="theme-swatch__sample" aria-hidden="true">♥ ✦</span><strong>{{ $theme['name'] }}</strong>@if($id === $activeId)<span>{{ __('Текущая тема') }}</span>@endif</a>
@endforeach
</div>
<a class="button button--quiet section-space" href="{{ route('admin.panel.themes', ['new'=>1]) }}">+ {{ __('Добавить тему') }}</a>
<x-card class="section-space"><h2>{{ __('Смена темы') }}</h2>
<form method="post" action="{{ route('admin.panel.appearance.update') }}" class="form-stack" data-submit-lock>@csrf @method('put')
<input type="hidden" name="section" value="selection"><input type="hidden" name="revision" value="{{ $revision }}">
<x-select-field name="active" :label="__('Ручная тема')">@foreach($settings['themes'] as $id=>$theme)<option value="{{ $id }}" @selected(old('active',$settings['active']) === $id)>{{ $theme['name'] }}</option>@endforeach</x-select-field>
<x-select-field name="seasonal" :label="__('Режим смены')"><option value="0" @selected(!old('seasonal',$settings['seasonal']))>{{ __('Ручной выбор') }}</option><option value="1" @selected(old('seasonal',$settings['seasonal']))>{{ __('Автоматически по сезонам') }}</option></x-select-field>
<p>{{ __('Сезоны: март–май, июнь–август, сентябрь–ноябрь, декабрь–февраль. Переход — в полночь по Минску при открытии или обновлении страницы.') }}</p>
@foreach(['spring'=>__('Весна'),'summer'=>__('Лето'),'autumn'=>__('Осень'),'winter'=>__('Зима')] as $season=>$label)
<x-select-field :name="'seasons['.$season.']'" :label="$label">@foreach($settings['themes'] as $id=>$theme)<option value="{{ $id }}" @selected(old('seasons.'.$season,$settings['seasons'][$season]) === $id)>{{ $theme['name'] }}</option>@endforeach</x-select-field>
@endforeach
<label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Применить оформление для всех пользователей') }}</label><x-button type="submit">{{ __('Применить тему') }}</x-button>
</form></x-card>
<x-card class="section-space"><h2>{{ $isNew ? __('Новая тема') : __('Редактировать тему') }}</h2>
<form method="post" action="{{ route('admin.panel.appearance.update') }}" enctype="multipart/form-data" class="form-stack" data-submit-lock data-theme-editor>@csrf @method('put')
<input type="hidden" name="section" value="theme"><input type="hidden" name="revision" value="{{ $revision }}"><input type="hidden" name="theme_id" value="{{ $editId }}">
<x-field name="theme[name]" :label="__('Название')" :value="old('theme.name',$isNew ? '' : $editTheme['name'])" maxlength="80" required />
<div class="theme-colors">@foreach(['primary'=>__('Основной цвет'),'ink'=>__('Текст'),'muted'=>__('Вторичный текст'),'lilac'=>__('Светлый акцент'),'butter'=>__('Жёлтые карточки'),'rose'=>__('Розовые карточки'),'background'=>__('Начало фона'),'gradient'=>__('Конец градиента')] as $key=>$label)
<x-field :name="'theme['.$key.']'" type="color" :label="$label" :value="old('theme.'.$key,$editTheme[$key])" data-theme-color="{{ $key }}" required />@endforeach</div>
<x-field name="theme[angle]" type="range" :label="__('Угол градиента')" :value="old('theme.angle',$editTheme['angle'])" min="0" max="360" data-theme-angle required />
<x-select-field name="theme[decoration]" :label="__('Украшения фона')" data-theme-decoration>@foreach(['none'=>__('Без украшений'),'clouds'=>__('Облака и сердечки'),'flowers'=>__('Цветы'),'sun'=>__('Солнце'),'leaves'=>__('Листья'),'snow'=>__('Снежинки'),'hearts'=>__('Сердечки'),'pixels'=>__('Пиксельные сердца')] as $value=>$label)<option value="{{ $value }}" @selected(old('theme.decoration',$editTheme['decoration']) === $value)>{{ $label }}</option>@endforeach</x-select-field>
<x-field name="image" type="file" :label="__('Своё фоновое изображение — PNG, JPEG или WebP, до 4 МБ')" accept="image/png,image/jpeg,image/webp" data-theme-image />
@if($editTheme['image'])<img class="theme-upload-preview" src="{{ route('appearance.asset',['asset'=>$editTheme['image']]) }}" alt="{{ __('Текущий фон') }}"><label class="confirmation-check"><input type="checkbox" name="remove_image" value="1">{{ __('Убрать своё фоновое изображение') }}</label>@endif
<div class="theme-live-preview card" data-theme-preview><strong>{{ __('Предпросмотр оформления') }}</strong><p>{{ __('Цвета и фон меняются сразу, сохранение — по кнопке ниже.') }}</p><span class="button button--primary">{{ __('Кнопка') }}</span></div>
<details><summary>{{ __('Иконки этой темы') }}</summary><p>{{ __('Пустое поле сохраняет обычную иллюстрацию. Общие замены в разделе «Иконки» имеют приоритет над темой.') }}</p><div class="theme-colors">@foreach(\App\Services\Appearance\AppearanceCatalog::ICONS as $icon)<x-field :name="'theme[icons]['.$icon.']'" :label="$catalog->iconLabels()[$icon]" :value="old('theme.icons.'.$icon,$editTheme['icons'][$icon] ?? '')" maxlength="12" />@endforeach</div></details>
<label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Сохранить тему для общего оформления') }}</label><x-button type="submit">{{ __('Сохранить тему') }}</x-button>
</form></x-card>
@endsection
