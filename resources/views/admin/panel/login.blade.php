@extends('layouts.app')
@section('title', __('Вход в админ-панель'))
@section('content')
<x-card class="auth-card form-stack"><h1>{{ __('Админ-панель') }}</h1><x-form-feedback />
<form method="post" action="{{ route('admin.panel.unlock') }}" class="form-stack" data-submit-lock>@csrf
<x-field name="password" type="password" :label="__('Пароль админ-панели')" required autocomplete="current-password" />
<x-button type="submit">{{ __('Войти') }}</x-button></form></x-card>
@endsection
