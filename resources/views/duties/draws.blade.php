@extends('layouts.app')
@section('title', __('Кто сделает?'))
@section('content')
<h1 class="screen-title">{{ __('Кто сделает?') }}</h1><x-form-feedback /><p class="lead">{{ __('Создайте дело для жеребьёвки. После согласия обоих случайно выберем исполнителя и добавим ему обязанность. Переиграть результат нельзя.') }}</p>
<x-card><form method="post" action="{{ route('chores.store',$couple) }}" class="form-stack" data-submit-lock>@csrf<input type="hidden" name="operation_key" value="{{ $operationKey }}"><x-field name="description" :label="__('Что нужно сделать')" :value="old('description')" maxlength="2000" required /><x-field name="due_at" type="datetime-local" :label="__('До какого времени')" :value="old('due_at')" required /><x-button type="submit">{{ __('Предложить жеребьёвку') }}</x-button></form></x-card>
@endsection
