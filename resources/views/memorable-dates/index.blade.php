@extends('layouts.app')
@section('title', __('Памятные даты'))
@section('content')
<h1 class="screen-title">{{ __('Памятные даты') }}</h1>
<p class="lead">{{ __('Годовщины и важные дни вашей пары. Напомним в Telegram за три дня и в сам день, в 09:00.') }}</p>
<x-form-feedback />
<h2 class="group-label"><span>{{ __('Ближайшие') }}</span><span>{{ $dates->count() }}</span></h2>
@foreach($dates as $date)
<div class="row">
    <x-icon name="date" />
    <div class="row__body"><strong class="row__title">{{ $date->title }}</strong><span class="row__meta">{{ $date->date->isoFormat('D MMMM YYYY') }}@if($date->years) · {{ __(':count-я годовщина', ['count'=>$date->years]) }}@endif</span></div>
    <x-status :tone="$date->daysLeft === 0 ? 'action' : ($date->daysLeft <= 3 ? 'warning' : 'neutral')">{{ $date->getCountdown() }}</x-status>
</div>
@if($canEdit && $date->id)<details class="settings-section"><summary><span class="row__body"><span class="row__meta">{{ __('Удалить дату «:title»', ['title'=>$date->title]) }}</span></span></summary><form method="post" action="{{ route('memorable_dates.destroy',[$couple,$date->id]) }}" class="form-stack" data-submit-lock>@csrf @method('delete')<label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю удаление даты') }}</label><x-button type="submit" variant="quiet">{{ __('Удалить') }}</x-button></form></details>@endif
@endforeach
<p class="field-hint section-space">{{ __('Даты начала отношений и свадьбы меняются на странице пары.') }} <a href="{{ route('couples.show',$couple) }}">{{ __('Открыть страницу пары') }}</a></p>
@if($canEdit)
<details class="settings-section section-space" @if($errors->any()) open @endif><summary><x-icon name="plus" /><span class="row__body"><span class="row__title">{{ __('Добавить дату') }}</span><span class="row__meta">{{ __('День рождения, первая встреча, поездка') }}</span></span></summary>
<form method="post" action="{{ route('memorable_dates.store',$couple) }}" class="form-stack" data-submit-lock>@csrf
<input type="hidden" name="operation_key" value="{{ old('operation_key',$operationKey) }}">
<x-field name="title" :label="__('Что за дата')" :value="old('title')" :error="$errors->first('title')" maxlength="120" required />
<x-field name="occurs_on" type="date" :label="__('Когда')" :value="old('occurs_on')" :error="$errors->first('occurs_on')" required />
<input type="hidden" name="is_yearly" value="0"><label class="confirmation-check"><input type="checkbox" name="is_yearly" value="1" @checked(old('is_yearly','1') === '1')>{{ __('Повторять каждый год') }}</label>
<x-button type="submit">{{ __('Сохранить дату') }}</x-button></form></details>
@endif
@endsection

@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
