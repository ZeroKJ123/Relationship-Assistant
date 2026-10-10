@extends('layouts.app')
@section('title', __('Пригласить партнёра'))
@section('content')
<x-card class="auth-card">
    <x-status>{{ __('Планы вдвоём') }}</x-status>
    <h1 class="form-title">{{ __('Свидание или событие') }}</h1>
    <p>{{ __('Обычное приглашение бесплатно и доступно при отрицательном балансе. Ответ и встречное условие согласуются отдельно.') }}</p>
    <x-form-feedback />
    <form method="post" action="{{ route('dates.store', $couple) }}" class="form-stack" data-submit-lock>
        @csrf
        <input type="hidden" name="operation_key" value="{{ old('operation_key', $operationKey) }}">
        <input type="hidden" name="request_template_id" value="{{ old('request_template_id', $preset?->id) }}">
        <fieldset class="settings-group">
            <legend>{{ __('Что запланируем') }}</legend>
            <div class="field">
                <label for="kind">{{ __('Вид приглашения') }}</label>
                <select id="kind" name="kind" @if($errors->has('kind')) aria-invalid="true" aria-describedby="kind-error" @endif>
                    @foreach($kinds as $kind)<option value="{{ $kind->value }}" @selected(old('kind', $preset ? 'activity' : 'date') === $kind->value)>{{ $kind->label() }}</option>@endforeach
                </select>
                @error('kind')<p class="field-error" id="kind-error">{{ $message }}</p>@enderror
            </div>
            <x-field name="title" :label="__('Название')" :value="old('title', $preset?->name ?? '')" :error="$errors->first('title')" maxlength="120" required />
            <x-textarea-field name="description" :label="__('Описание — если нужно')" :value="old('description')" :error="$errors->first('description')" maxlength="2000" />
            <x-field name="dress_code" :label="__('Дресс-код — если нужен')" :value="old('dress_code', '')" :error="$errors->first('dress_code')" maxlength="500" />
        </fieldset>
        <fieldset class="settings-group">
            <legend>{{ __('Когда встретимся') }}</legend>
            <x-field name="starts_at" :label="__('Дата и время')" type="datetime-local" :value="old('starts_at', '')" :error="$errors->first('starts_at')" aria-describedby="starts-at-hint" required />
            <p class="field-hint" id="starts-at-hint">{{ __('Часовой пояс: :zone', ['zone' => $couple->timezone]) }}</p>
            <p class="field-hint">{{ __('Принятие приглашения не означает выполнение. Время нельзя изменить односторонне: используйте общую отмену и новое приглашение. Срок выполнения — время события.') }}</p>
        </fieldset>
        <label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю приглашение и время') }}</label>
        <x-button type="submit">{{ __('Отправить приглашение') }}</x-button>
    </form>
</x-card>
<a class="button button--quiet section-space" href="{{ route('couples.show', $couple) }}">{{ __('К нашей паре') }}</a>
@endsection
@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
