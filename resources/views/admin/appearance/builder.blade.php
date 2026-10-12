@extends('layouts.admin')
@section('title', __('Конструктор главной'))
@section('admin-content')
<h1 class="screen-title">{{ __('Конструктор главной') }}</h1><p>{{ __('Перетаскивайте блоки за ручку ⠿ или используйте стрелки. Клик по блоку в предпросмотре открывает его настройки. Снимите «Показывать», чтобы убрать элемент; включите снова, чтобы вернуть. Изменения применяются после сохранения.') }}</p>
<form method="post" action="{{ route('admin.panel.appearance.update') }}" class="form-stack" data-builder data-submit-lock>@csrf @method('put')
<input type="hidden" name="section" value="layout"><input type="hidden" name="revision" value="{{ $revision }}">
<div class="builder-toolbar"><x-select-field name="layout[columns]" :label="__('Колонок быстрых действий')" data-builder-columns>@for($columns=1;$columns<=4;$columns++)<option value="{{ $columns }}" @selected($settings['layout']['columns'] === $columns)>{{ $columns }}</option>@endfor</x-select-field><x-select-field name="preview-width" :label="__('Ширина предпросмотра')" data-preview-width><option value="wide">{{ __('Компьютер') }}</option><option value="narrow">{{ __('Телефон') }}</option></x-select-field></div>
<div class="builder-workspace"><div class="builder-controls"><h2>{{ __('Блоки страницы') }}</h2>
<div class="form-stack" data-builder-list="blocks">@foreach($settings['layout']['blocks'] as $index=>$item)<x-builder-item :item="$item" :index="$index" group="blocks" :catalog="$catalog" />@endforeach</div>
<button type="button" class="button button--quiet section-space" data-add-text>+ {{ __('Добавить текстовую карточку') }}</button>
<h2 class="section-space">{{ __('Иконки быстрых действий') }}</h2><div class="form-stack" data-builder-list="tiles">@foreach($settings['layout']['tiles'] as $index=>$item)<x-builder-item :item="$item" :index="$index" group="tiles" :catalog="$catalog" />@endforeach</div>
</div><div class="builder-preview"><p>{{ __('Главная текущего аккаунта. Пустые блоки обозначены карточками; условия в примере можно переключить ниже.') }}</p>
<div class="builder-scenarios">@foreach(['has_partner'=>__('Есть партнёр'),'has_car'=>__('Есть машина'),'positive_balance'=>__('Баланс > 0'),'has_duties'=>__('Есть дела'),'has_request'=>__('Есть просьба')] as $key=>$label)<label><input type="checkbox" data-preview-condition="{{ $key }}" @checked(match($key){'has_partner'=>$dashboard->partner !== null,'has_car'=>$dashboard->hasCar,'positive_balance'=>$dashboard->wallet->balance>0,'has_duties'=>$dashboard->duties->isNotEmpty(),'has_request'=>$dashboard->newRequest !== null})>{{ $label }}</label>@endforeach</div>
<iframe title="{{ __('Предпросмотр главной') }}" sandbox="allow-same-origin" data-builder-preview srcdoc="{{ view('admin.appearance.preview',['dashboard'=>$dashboard,'appearance'=>$appearance,'catalog'=>$catalog])->render() }}"></iframe>
<p role="status" data-builder-status>{{ __('Изменения пока не сохранены на сайте.') }}</p>
</div></div>
<template data-text-template><x-builder-item :item="['id'=>'text-template','type'=>'text','enabled'=>true,'condition'=>'always','tone'=>'plain','title'=>'','text'=>'']" :index="0" group="blocks" :catalog="$catalog" /></template>
<label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Применить главную страницу для всех пользователей') }}</label><x-button type="submit">{{ __('Сохранить главную') }}</x-button>
</form>
@endsection
