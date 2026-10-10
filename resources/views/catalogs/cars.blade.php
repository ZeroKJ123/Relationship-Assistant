@extends('layouts.app')
@section('title', __('Мои машины'))
@section('content')
<h1>{{ __('Мои машины') }}</h1><x-form-feedback /><p>{{ __('Добавьте свою машину. В ответе на просьбу появится способ оплаты заправкой; объём или бюджет укажите в деталях условия. Выбор требует согласия партнёра.') }}</p>
<div class="product-grid">@forelse($cars as $car)<x-card><h2>{{ $car->name }}</h2><p>{{ __($fuels[$car->fuel]) }}</p></x-card>@empty<x-card><p>{{ __('Машин пока нет.') }}</p></x-card>@endforelse</div>{{ $cars->links() }}
<x-card class="section-space"><h2>{{ __('Добавить машину') }}</h2><form method="post" action="{{ route('cars.store') }}" class="form-stack" data-submit-lock>@csrf<input type="hidden" name="operation_key" value="{{ old('operation_key',$operationKey) }}"><x-field name="name" :label="__('Марка, модель или название машины')" :value="old('name')" maxlength="60" required /><div class="field"><label for="fuel">{{ __('Топливо') }}</label><select id="fuel" name="fuel">@foreach($fuels as $value=>$label)<option value="{{ $value }}" @selected(old('fuel') === $value)>{{ __($label) }}</option>@endforeach</select></div><x-button type="submit">{{ __('Добавить машину') }}</x-button></form></x-card>
<a class="button button--quiet section-space" href="{{ route('home') }}">{{ __('На главную') }}</a>
@endsection
