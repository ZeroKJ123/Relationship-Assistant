@extends('layouts.app')
@section('title', __('Мой профиль · Наша пара'))
@section('content')
<x-card class="auth-card">
    <x-avatar :name="$user->name" :src="$user->avatar_path !== null ? route('profile.avatar', ['v'=>$user->updated_at?->getTimestamp()]) : null" />
    <form method="post" enctype="multipart/form-data" action="{{ route('profile.avatar.store') }}" class="form-stack" data-submit-lock>
        @csrf
        <label for="profile-photo">{{ __('Фото профиля') }}</label>
        <input id="profile-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required aria-describedby="profile-photo-hint">
        <p id="profile-photo-hint" class="field-hint">{{ __('Выберите фото из галереи: JPEG, PNG или WebP до 4 МБ.') }}</p>
        @error('photo')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        <x-button type="submit" variant="quiet">{{ __('Сохранить фото') }}</x-button>
    </form>
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
        @elseif($telegramLinkAvailable)
            <p>{{ __('Отправьте /start своему боту, скопируйте полученный код и вставьте его здесь. Привязывайте только свой Telegram. Код действует 10 минут.') }}</p>
            @if($telegramBotUrl)<a class="button button--quiet" href="{{ $telegramBotUrl }}" target="_blank" rel="noopener noreferrer">{{ __('Открыть бота') }}</a>@endif
            <form method="post" action="{{ route('profile.telegram.link') }}" class="form-stack" data-submit-lock>
                @csrf
                <x-field name="telegram_code" :label="__('Код из Telegram')" :error="$errors->first('telegram_code')" required maxlength="36" autocomplete="off" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" />
                <x-button type="submit">{{ __('Связать Telegram') }}</x-button>
            </form>
            @if($telegramAvailable)
            <details class="settings-history">
            <summary>{{ __('Другой способ привязки') }}</summary>
            <p>{{ __('Привяжите свой аккаунт, чтобы получать уведомления и отвечать через бота. Привязка требует подтверждения в Telegram и в этом браузере.') }}</p>
            <form method="post" action="{{ route('telegram.login.store') }}" data-submit-lock>@csrf<x-button type="submit" variant="quiet">{{ __('Привязать Telegram') }}</x-button></form>
            </details>
            @endif
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
        @if($couple)
            <a href="{{ route('mood.index', $couple) }}">{{ __('Настроение и расписание') }} <span aria-hidden="true">→</span></a>
            <a href="{{ route('reminders.index', $couple) }}">{{ __('Напоминания пары') }} <span aria-hidden="true">→</span></a>
        @endif
        @can('manage-system-settings')<a href="{{ route('admin.economy.index') }}">{{ __('Экономика приложения') }} <span aria-hidden="true">→</span></a>@endcan
    </nav>
    <form method="post" action="{{ route('logout') }}" class="form-footer" data-submit-lock>@csrf<x-button type="submit" variant="quiet">{{ __('Выйти') }}</x-button></form>
</x-card>
<x-card class="auth-card"><details class="advanced-settings"><summary>{{ __('Подробные настройки') }}</summary>
    <section class="form-stack"><h2>{{ __('Машины') }}</h2>@foreach($cars as $car)<p>{{ $car->name }} · {{ $car->fuel }}</p>@endforeach
    <a class="button button--quiet" href="{{ route('cars.index') }}">{{ $cars->isEmpty() ? __('Добавить машину') : __('Управлять машинами') }}</a></section>
    @if($couple)
    <section class="form-stack"><h2>{{ __('Настройки пары') }}</h2><a href="{{ route('couples.show',$couple) }}">{{ __('Даты, приглашения и договорённости') }}</a></section>
    @if($couple->status === \App\Enums\CoupleStatus::Active)
    <form method="post" action="{{ route('profile.timezone',$couple) }}" class="form-stack" data-submit-lock>@csrf @method('patch')
    <input type="hidden" name="expected_timezone" value="{{ $couple->timezone }}">
    <x-field name="timezone" :label="__('Часовой пояс пары')" :value="old('timezone',$couple->timezone)" :error="$errors->first('timezone')" required maxlength="64" />
    <p class="field-hint">{{ __('Например, Europe/Minsk. Определяет время встреч и напоминаний для вас обоих.') }}</p>
    <label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю изменение времени пары') }}</label>
    <x-button type="submit" variant="quiet">{{ __('Сохранить часовой пояс') }}</x-button></form>
    @endif
    @else<section class="form-stack"><a href="{{ route('couples.create') }}">{{ __('Пригласить партнёра') }}</a></section>@endif
</details></x-card>
<x-card class="auth-card form-stack"><h2>{{ __('Комплименты партнёру') }}</h2><p>{{ __('Эти слова будут появляться у партнёра на главной и меняться каждые пять минут.') }}</p><form method="post" action="{{ route('profile.compliments.store') }}" class="form-stack" data-submit-lock data-compliment-editor>@csrf
<div data-compliment-fields>@foreach(old('compliments',$user->compliments ?? []) as $text)<x-field name="compliments[]" :id="'compliment-'.$loop->index" :label="__('Комплимент')" :value="$text" maxlength="300" />@endforeach<x-field name="compliments[]" id="compliment-new" :label="__('Новый комплимент')" maxlength="300" /></div>
<x-button type="button" variant="quiet" data-add-compliment>{{ __('Добавить ещё') }}</x-button><x-button type="submit">{{ __('Сохранить комплименты') }}</x-button></form><p>{{ __('Очистите строку, чтобы удалить комплимент.') }}</p></x-card>
<x-pwa-help />
@endsection
@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
