@extends('layouts.app')
@section('content')
<div class="admin-shell">
<nav class="admin-sidebar" aria-label="{{ __('Разделы админ-панели') }}">
<a class="brand" href="{{ route('admin.panel.index') }}">{{ __('Админ-панель') }}</a>
@foreach(['index'=>['▦',__('Обзор и тесты')], 'balance.index'=>['♥',__('Баланс')], 'themes'=>['◐',__('Темы')], 'icons'=>['✦',__('Иконки')], 'builder'=>['▤',__('Конструктор главной')]] as $key=>$item)
<a href="{{ route('admin.panel.'.$key) }}" @if(request()->routeIs('admin.panel.'.$key)) aria-current="page" @endif><span aria-hidden="true">{{ $item[0] }}</span>{{ $item[1] }}</a>
@endforeach
@can('manage-system-settings')<a href="{{ route('admin.economy.index') }}" @if(request()->routeIs('admin.economy.*')) aria-current="page" @endif><span aria-hidden="true">⚙</span>{{ __('Экономика') }}</a>@endcan
<a href="{{ route('home') }}">{{ __('Открыть сайт') }} →</a>
<form method="post" action="{{ route('admin.panel.lock') }}" data-submit-lock>@csrf<x-button type="submit" variant="quiet">{{ __('Закрыть панель') }}</x-button></form>
</nav>
<div class="admin-content"><x-form-feedback />@yield('admin-content')</div>
</div>
@endsection
