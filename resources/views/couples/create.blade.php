@extends('layouts.app')
@section('title', __('Создать пару · Наша пара'))
@section('content')
<x-card class="auth-card">
    <p class="eyebrow">{{ __('Начало вашей истории') }}</p>
    <h1 class="form-title">{{ __('Пригласить партнёра') }}</h1>
    <p>{{ __('Укажите, когда вы стали вместе. Ссылка действует 7 суток. Новая ссылка отменяет предыдущую. Одновременно можно состоять только в одной паре.') }}</p>
    <form method="post" action="{{ route('invitations.store') }}" class="form-stack" data-submit-lock>
        @csrf
        <x-field name="started_on" type="date" :label="__('Вместе с')" :value="old('started_on')" :error="$errors->first('started_on')" required />
        <x-field name="timezone" :label="__('Часовой пояс')" :value="old('timezone', $defaultTimezone)" :error="$errors->first('timezone')" required maxlength="64" aria-describedby="zone-help" />
        <p id="zone-help" class="field-hint">{{ __('Например, Europe/Minsk или Asia/Almaty. Он определяет даты вашей пары.') }}</p>
        @error('couple')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        <x-button type="submit">{{ __('Создать ссылку') }}</x-button>
    </form>
    <p class="form-footer"><a href="{{ route('couples.index') }}">{{ __('Мои пары') }}</a></p>
</x-card>
@endsection
