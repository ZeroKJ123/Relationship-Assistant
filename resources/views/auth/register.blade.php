@extends('layouts.app')
@section('title', __('Создать аккаунт · Любимость'))
@section('content')
<x-card class="auth-card">
    <p class="eyebrow">{{ __('Ваша история начинается здесь') }}</p>
    <h1 class="form-title">{{ __('Давайте знакомиться') }}</h1>
    <p>{{ __('Создайте личный аккаунт. Email не нужен.') }}</p>
    <form method="post" action="{{ route('register.store') }}" class="form-stack" data-submit-lock>
        @csrf
        <x-field name="name" :label="__('Ваше имя')" :value="old('name')" :error="$errors->first('name')" required maxlength="80" autocomplete="name" />
        <x-field name="login" :label="__('Логин')" :value="old('login')" :error="$errors->first('login')" required minlength="3" maxlength="40" autocomplete="username" pattern="[a-zA-Z0-9_]+" aria-describedby="login-hint" />
        <p id="login-hint" class="field-hint">{{ __('3–40 латинских букв, цифр или знак _.') }}</p>
        <x-field name="password" type="password" :label="__('Пароль')" :error="$errors->first('password')" required minlength="12" maxlength="128" autocomplete="new-password" aria-describedby="password-hint" />
        <p id="password-hint" class="field-hint">{{ __('Не менее 12 символов, с буквами и цифрами.') }}</p>
        <x-field name="password_confirmation" type="password" :label="__('Повторите пароль')" required minlength="12" maxlength="128" autocomplete="new-password" />
        <x-gender-field :value="old('gender', 'unspecified')" :error="$errors->first('gender')" />
        <x-button type="submit">{{ __('Создать аккаунт') }}</x-button>
    </form>
    <p class="form-footer">{{ __('Уже есть аккаунт?') }} <a href="{{ route('login') }}">{{ __('Войти') }}</a></p>
    @if($telegramAvailable)
        <form method="post" action="{{ route('telegram.login.store') }}" class="form-footer" data-submit-lock>@csrf<x-button type="submit" variant="quiet">{{ __('Войти через Telegram') }}</x-button></form>
    @endif
</x-card>
@endsection
