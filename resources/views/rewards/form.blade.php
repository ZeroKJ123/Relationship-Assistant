@extends('layouts.app')
@section('title', __('Награда'))
@section('back', route('rewards.index'))
@section('back-label', __('Магазин наград'))
@section('content')
<div class="auth-card"><h1 class="form-title">{{ $reward ? __('Изменить награду') : __('Добавить награду') }}</h1><x-form-feedback />
<form method="post" action="{{ $reward ? route('rewards.update',$reward) : route('rewards.store') }}" class="form-stack" data-submit-lock>@csrf @if($reward) @method('put') @endif
<input type="hidden" name="operation_key" value="{{ old('operation_key',$operationKey) }}"><input type="hidden" name="expected_version" value="{{ old('expected_version',$reward?->version ?? 0) }}">
<x-field name="title" :label="__('Что вы сделаете')" :value="old('title',$reward?->title ?? '')" :error="$errors->first('title')" maxlength="120" required />
<x-textarea-field name="description" :label="__('Подробности — по желанию')" :value="old('description',$reward?->description ?? '')" :error="$errors->first('description')" maxlength="500" />
<x-field name="heart_price" type="number" :label="__('Цена в сердечках')" :value="old('heart_price',$reward?->heart_price ?? '')" :error="$errors->first('heart_price')" min="1" max="1000" step="1" required />
<x-button type="submit">{{ __('Сохранить награду') }}</x-button></form>
@if($reward && !$reward->archived_at)<details class="settings-section section-space"><summary><span class="row__body"><span class="row__title">{{ __('Убрать из магазина') }}</span></span></summary><form method="post" action="{{ route('rewards.destroy',$reward) }}" class="form-stack" data-submit-lock>@csrf @method('delete')<input type="hidden" name="expected_version" value="{{ $reward->version }}"><label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю: награда больше не будет продаваться') }}</label><x-button type="submit" variant="quiet">{{ __('Убрать награду') }}</x-button></form></details>@endif
</div>
@endsection
