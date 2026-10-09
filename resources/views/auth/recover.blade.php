@extends('layouts.app')
@section('title', __('Восстановить доступ · Наша пара'))
@section('content')
<x-card class="auth-card">
    <p class="eyebrow">{{ __('Вернёмся к вашей истории') }}</p>
    <h1 class="form-title">{{ __('Новый пароль') }}</h1>
    <p>{{ __('Введите один из резервных кодов, сохранённых при регистрации. Код можно использовать один раз.') }}</p>
    <form method="post" action="{{ route('recover.store') }}" class="form-stack" data-submit-lock>
        @csrf
        <x-field name="login" :label="__('Логин')" :value="old('login')" :error="$errors->first('login')" required maxlength="40" autocomplete="username" />
        <x-field name="recovery_code" :label="__('Резервный код')" :error="$errors->first('recovery_code')" required minlength="24" maxlength="24" autocomplete="off" spellcheck="false" />
        <x-field name="password" type="password" :label="__('Новый пароль')" :error="$errors->first('password')" required minlength="12" maxlength="128" autocomplete="new-password" />
        <p class="field-hint">{{ __('Не менее 12 символов, с буквами и цифрами.') }}</p>
        <x-field name="password_confirmation" type="password" :label="__('Повторите пароль')" required minlength="12" maxlength="128" autocomplete="new-password" />
        <x-button type="submit">{{ __('Восстановить доступ') }}</x-button>
    </form>
    <p class="form-footer"><a href="{{ route('login') }}">{{ __('Вернуться ко входу') }}</a></p>
</x-card>
@endsection
