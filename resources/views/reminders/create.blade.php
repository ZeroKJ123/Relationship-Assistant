@extends('layouts.app')
@section('title', __('Напомнить партнёру'))
@section('content')
<x-card class="auth-card">
    <x-status>{{ __('Забота о партнёре') }}</x-status>
    <h1 class="form-title">{{ __('Напомнить') }}</h1>
    <p>{{ __('Получатель — ваш партнёр. Напоминание разовое; отмена доступна до начала отправки. Для изменения отмените его и создайте новое.') }}</p>
    <x-form-feedback />
    <form method="post" action="{{ route('reminders.store', $couple) }}" class="form-stack" data-submit-lock>
        @csrf
        <input type="hidden" name="operation_key" value="{{ old('operation_key', $operationKey) }}">
        <x-textarea-field name="text" :label="__('Текст напоминания')" :value="old('text')" :error="$errors->first('text')" maxlength="2000" rows="4" required />
        <x-field name="due_at" :label="__('Когда напомнить')" type="datetime-local" :value="old('due_at')" :error="$errors->first('due_at')" aria-describedby="due-at-hint" required />
        <p class="field-hint" id="due-at-hint">{{ __('Часовой пояс: :zone', ['zone' => $couple->timezone]) }}</p>
        <x-card tone="yellow"><p>{{ __('Запись сохранится в приложении. Доставка в Telegram требует подключённого чата партнёра и работающей отправки уведомлений. Запланировано — ещё не значит доставлено. Опоздавшие больше чем на сутки напоминания не отправляются.') }}</p></x-card>
        <label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю текст и время') }}</label>
        <x-button type="submit">{{ __('Запланировать') }}</x-button>
    </form>
</x-card>
<a class="button button--quiet section-space" href="{{ route('reminders.index', $couple) }}">{{ __('Все напоминания') }}</a>
@endsection
@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
