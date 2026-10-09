@extends('layouts.app')
@section('title', __('Завершить пару · Наша пара'))
@section('content')
<x-card class="auth-card">
    <h1 class="form-title">{{ __('Завершить пару?') }}</h1>
    <p>{{ __('Это освободит обоих участников для новой пары и отменит приглашения. История сохранится для вас двоих; новый партнёр не получит к ней доступа.') }}</p>
    <form method="post" action="{{ route('couples.end_store', $couple) }}" class="form-stack" data-submit-lock>
        @csrf
        <label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required> {{ __('Подтверждаю завершение пары') }}</label>
        @error('confirmation')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        @error('couple')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        <x-button type="submit" variant="danger">{{ __('Завершить пару') }}</x-button>
    </form>
    <p class="form-footer"><a href="{{ route('couples.show', $couple) }}">{{ __('Остаться в паре') }}</a></p>
</x-card>
@endsection
