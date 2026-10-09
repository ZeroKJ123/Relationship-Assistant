@extends('layouts.app')
@section('title', __('Новая просьба'))
@section('content')
<x-card class="auth-card">
<h1>{{ __('Новая просьба') }}</h1>
<p>{{ __('Срок указывается по часовому поясу пары: :zone', ['zone'=>$couple->timezone]) }}</p>
@if($errors->any())<div role="alert">
@foreach($errors->all() as $error)<p class="field-error">{{ $error }}</p>
@endforeach</div>
@endif
<form method="post" action="{{ route('requests.store',$couple) }}" class="form-stack" data-submit-lock>
@csrf
<input type="hidden" name="operation_key" value="{{ old('operation_key',$operationKey) }}">
<div class="field">
<label for="type">{{ __('О чём просьба') }}</label>
<select id="type" name="type">
@foreach($types as $type)<option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ __($type->label()) }}</option>
@endforeach</select>
</div>
<div class="field">
<label for="description">{{ __('Что нужно сделать') }}</label>
<textarea id="description" name="description" required maxlength="2000" rows="4">{{ old('description') }}</textarea>
</div>
<x-field name="due_at" :label="__('Выполнить до')" type="datetime-local" :value="old('due_at','')" required />
<label class="confirmation-check"><input type="checkbox" name="is_very_wanted" value="1" @checked(old('is_very_wanted'))>{{ __('Очень хочу') }}</label>
<p>{{ __('Для «Очень хочу» нужны согласованный недельный лимит и баланс без минуса. Обычный отказ партнёра уменьшает его сердечки; отсутствие денег на продукты — без штрафа.') }}</p>
<a href="{{ route('agreements.show',$couple) }}">{{ __('Наш недельный лимит') }}</a>
<x-button type="submit">{{ __('Отправить просьбу') }}</x-button>
</form>
</x-card>
<a class="button button--quiet section-space" href="{{ route('requests.index',$couple) }}">{{ __('Все просьбы') }}</a>
@endsection
