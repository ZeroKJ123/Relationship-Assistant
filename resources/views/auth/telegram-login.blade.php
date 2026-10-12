@extends('layouts.app')
@section('title', __('Подтверждение Telegram · Любимость'))
@section('content')
<x-card class="auth-card">
    <h1 class="form-title">{{ $attempt->user_id === null ? __('Войти через Telegram') : __('Привязать Telegram') }}</h1>
    <x-form-feedback />
    @if($attempt->expires_at->lessThanOrEqualTo(now()))
        <p role="status">{{ __('Попытка истекла. Начните новую.') }}</p>
        <form method="post" action="{{ route('telegram.login.store') }}" data-submit-lock>@csrf<x-button type="submit">{{ __('Начать заново') }}</x-button></form>
    @else
        <p>{{ __('Ссылка действует 10 минут. Откройте бота, сверьте код и подтвердите попытку, которую начали сами.') }}</p>
        <p>{{ __('Код для сверки') }}: <strong>{{ $attempt->verification_code }}</strong></p>
        @if($attempt->status === \App\Enums\TelegramLoginStatus::Pending)
            <a class="button button--primary" href="{{ $deepLink }}" rel="noreferrer noopener" target="_blank">{{ __('Открыть бота') }}</a>
        @endif
        @if($attempt->status === \App\Enums\TelegramLoginStatus::Confirmed)
            <p role="status">{{ __('Подтверждено в Telegram. Имя: :name', ['name' => $attempt->identity['name'] ?? '']) }}</p>
            <p>{{ __('Завершайте только если это ваш аккаунт Telegram. При несовпадении отмените попытку.') }}</p>
            <form method="post" action="{{ route('telegram.login.update', $attempt->id) }}" data-submit-lock>@csrf @method('patch')<x-button type="submit">{{ $attempt->user_id === null ? __('Завершить вход') : __('Завершить привязку') }}</x-button></form>
        @else
            <p role="status">{{ __('Ожидаем подтверждение в боте. После подтверждения обновите статус.') }}</p>
            <a class="button button--quiet" href="{{ route('telegram.login.show', $attempt->id) }}">{{ __('Обновить статус') }}</a>
        @endif
    @endif
    <form method="post" action="{{ route('telegram.login.destroy', $attempt->id) }}" class="form-footer" data-submit-lock>@csrf @method('delete')<x-button type="submit" variant="quiet">{{ __('Отмена') }}</x-button></form>
</x-card>
@endsection
