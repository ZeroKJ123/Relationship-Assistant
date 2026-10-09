@extends('layouts.app')
@section('title', __('Мой профиль · Наша пара'))
@section('content')
<x-card class="auth-card">
    <x-avatar :name="$user->name" />
    <h1 class="form-title">{{ __('Привет, :name', ['name' => $user->name]) }}</h1>
    <p>{{ __('Здесь начинается ваше личное пространство.') }}</p>
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    @if($recoveryCodes !== [])
        <x-card tone="yellow" class="recovery-card">
            <h2>{{ __('Сохраните резервные коды') }}</h2>
            <p>{{ __('Они помогут восстановить доступ без email. Каждый код работает один раз; при следующем открытии страницы этот список исчезнет. Храните коды в безопасном месте.') }}</p>
            <ul class="recovery-codes">@foreach($recoveryCodes as $code)<li><code>{{ $code }}</code></li>@endforeach</ul>
        </x-card>
    @endif
    <form method="post" action="{{ route('profile.update') }}" class="form-stack" data-submit-lock>
        @csrf @method('patch')
        <x-field name="name" :label="__('Имя')" :value="old('name', $user->name)" :error="$errors->first('name')" required maxlength="80" autocomplete="name" />
        <x-gender-field :value="old('gender', $user->gender?->value ?? 'unspecified')" :error="$errors->first('gender')" />
        <div class="field">
            <label for="payment_details">{{ __('Реквизиты для переводов') }}</label>
            <textarea id="payment_details" name="payment_details" maxlength="1000" rows="3" @if($errors->has('payment_details')) aria-invalid="true" aria-describedby="payment-details-error" @endif>{{ old('payment_details', $user->payment_details) }}</textarea>
            <p class="field-hint">{{ __('Укажите только данные для получения перевода. Не сохраняйте пароли, PIN или код с обратной стороны карты.') }}</p>
            @error('payment_details')<p id="payment-details-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <x-button type="submit">{{ __('Сохранить профиль') }}</x-button>
    </form>
    <p class="form-footer">{{ __('Создание пары и Telegram появятся на следующих этапах.') }}</p>
    <form method="post" action="{{ route('logout') }}" class="form-footer">@csrf<x-button type="submit" variant="quiet">{{ __('Выйти') }}</x-button></form>
</x-card>
@endsection
