@extends('layouts.app')
@section('title', __('Мой профиль · Наша пара'))
@section('content')
<x-card class="auth-card">
    <x-avatar :name="$user->name" :src="$user->avatar_path !== null ? route('profile.avatar') : null" />
    <h1 class="form-title">{{ __('Привет, :name', ['name' => $user->name]) }}</h1>
    <p>{{ __('Личные данные, реквизиты и связь с Telegram — всё в одном месте.') }}</p>
    <x-form-feedback />
    @if($recoveryCodes !== [])
        <x-card tone="yellow" class="recovery-card">
            <h2>{{ __('Сохраните резервные коды') }}</h2>
            <p>{{ __('Они помогут восстановить доступ без email. Каждый код работает один раз; при следующем открытии страницы этот список исчезнет. Храните коды в безопасном месте.') }}</p>
            <ul class="recovery-codes">@foreach($recoveryCodes as $code)<li><code>{{ $code }}</code></li>@endforeach</ul>
        </x-card>
    @endif
    <form method="post" action="{{ route('profile.update') }}" class="form-stack" data-submit-lock>
        @csrf @method('patch')
        <fieldset class="settings-group">
            <legend>{{ __('Обо мне') }}</legend>
            <x-field name="name" :label="__('Имя')" :value="old('name', $user->name)" :error="$errors->first('name')" required maxlength="80" autocomplete="name" />
            <x-gender-field :value="old('gender', $user->gender?->value ?? 'unspecified')" :error="$errors->first('gender')" />
            <x-currency-field :currencies="$currencies" :value="old('currency', $user->currency?->value ?? 'BYN')" :label="__('Моя валюта по умолчанию')" :error="$errors->first('currency')" />
            <p class="field-hint">{{ __('Это личная настройка. Старые суммы сохраняют свою валюту; приложение не пересчитывает деньги по курсу.') }}</p>
            @if($couple)<p class="field-hint">{{ __('В формах вашей пары используется валюта пары: :currency. Личная настройка её не меняет.', ['currency' => $couple->currency->value]) }}</p>@endif
        </fieldset>
        <fieldset class="settings-group">
            <legend>{{ __('Для получения переводов') }}</legend>
            <x-textarea-field name="payment_details" :label="__('Реквизиты для переводов')" :value="old('payment_details', $user->payment_details)" :error="$errors->first('payment_details')" :hint="__('Укажите только данные для получения перевода. Не сохраняйте пароли, PIN или код с обратной стороны карты.')" maxlength="1000" />
            <p class="field-hint">{{ __('При принятии денежной договорённости сохраняется копия реквизитов. Изменение профиля не заменяет данные в уже принятой просьбе.') }}</p>
        </fieldset>
        <x-button type="submit">{{ __('Сохранить профиль') }}</x-button>
    </form>
    <section class="form-stack" aria-labelledby="telegram-heading">
        <h2 id="telegram-heading">{{ __('Связь с Telegram') }}</h2>
        @if($hasTelegram)
            <x-status tone="success">{{ __('Telegram привязан') }}</x-status>
            <p>{{ __('Уведомления отправляются в подключённый чат. Состояние доставки напоминаний можно посмотреть в приложении.') }}</p>
        @elseif($telegramAvailable)
            <p>{{ __('Привяжите свой аккаунт, чтобы получать уведомления и отвечать через бота. Привязка требует подтверждения в Telegram и в этом браузере.') }}</p>
            <form method="post" action="{{ route('telegram.login.store') }}" data-submit-lock>@csrf<x-button type="submit" variant="quiet">{{ __('Привязать Telegram') }}</x-button></form>
        @else
            <x-status>{{ __('Подключение временно недоступно') }}</x-status>
            <p>{{ __('Вы можете пользоваться приложением. Привязка появится, когда подключение к боту будет настроено.') }}</p>
        @endif
    </section>
    @if($pendingInvitationUrl)<p class="form-footer"><a href="{{ $pendingInvitationUrl }}">{{ __('Продолжить принятие приглашения') }}</a></p>@endif
</x-card>
<x-card class="auth-card">
    <h2>{{ __('Моё пространство') }}</h2>
    <nav class="settings-links" aria-label="{{ __('Разделы профиля') }}">
        <a href="{{ route('hearts.index') }}">{{ __('Сердечки и история') }} <span aria-hidden="true">→</span></a>
        <a href="{{ route('dishes.index') }}">{{ __('Моя кухня') }} <span aria-hidden="true">→</span></a>
        <a href="{{ route('wishlist.index') }}">{{ __('Мой виш-лист') }} <span aria-hidden="true">→</span></a>
        <a href="{{ route('friends.index') }}">{{ __('Мои друзья') }} <span aria-hidden="true">→</span></a>
        <a href="{{ route('couples.index') }}">{{ __('Мои пары') }} <span aria-hidden="true">→</span></a>
        @if($couple)
            <a href="{{ route('mood.index', $couple) }}">{{ __('Настроение и расписание') }} <span aria-hidden="true">→</span></a>
            <a href="{{ route('reminders.index', $couple) }}">{{ __('Напоминания пары') }} <span aria-hidden="true">→</span></a>
        @endif
        @can('manage-system-settings')<a href="{{ route('admin.economy.index') }}">{{ __('Экономика приложения') }} <span aria-hidden="true">→</span></a>@endcan
    </nav>
    <form method="post" action="{{ route('logout') }}" class="form-footer" data-submit-lock>@csrf<x-button type="submit" variant="quiet">{{ __('Выйти') }}</x-button></form>
</x-card>
<x-pwa-help />
@endsection
@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
