@extends('layouts.app')
@section('title', __('Войти · Любимость'))
@section('content')
<x-card class="auth-card">
    <p class="eyebrow">{{ __('Рады видеть вас снова') }}</p>
    <h1 class="form-title">{{ __('Добро пожаловать') }}</h1>
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    <form method="post" action="{{ route('login.store') }}" class="form-stack" data-submit-lock>
        @csrf
        <x-field name="login" :label="__('Логин')" :value="old('login')" :error="$errors->first('login')" required maxlength="40" autocomplete="username" />
        <x-field name="password" type="password" :label="__('Пароль')" :error="$errors->first('password')" required maxlength="128" autocomplete="current-password" />
        <x-button type="submit">{{ __('Войти') }}</x-button>
    </form>
    <p class="form-footer"><a href="{{ route('recover') }}">{{ __('Забыли пароль?') }}</a></p>
    <p class="form-footer">{{ __('Пока нет аккаунта?') }} <a href="{{ route('register') }}">{{ __('Создать') }}</a></p>
    @if($telegramAvailable)
        <form method="post" action="{{ route('telegram.login.store') }}" class="form-footer" data-submit-lock>@csrf<x-button type="submit" variant="quiet">{{ __('Войти через Telegram') }}</x-button></form>
    @endif
</x-card>
@endsection
