@extends('layouts.app')
@section('title', __('Блюдо'))
@section('content')
<x-card class="auth-card">
<h1 class="form-title">{{ $item ? __('Редактировать карточку') : __('Новая карточка') }}</h1>
<x-form-feedback />
<form method="post" enctype="multipart/form-data" action="{{ $item ? route('dishes.update',$item) : route('dishes.store') }}" class="form-stack" data-submit-lock data-dish-editor data-photo-invalid="{{ __('Выберите JPEG, PNG или WebP до 4 МБ.') }}" data-photo-checking="{{ __('Проверяем фото…') }}" data-photo-ready="{{ __('Фото выбрано. Сохраните карточку, чтобы загрузить его.') }}" data-photo-failed="{{ __('Не удалось прочитать фото. Выберите другой файл.') }}">
@csrf @if($item) @method('put') @endif
<input type="hidden" name="operation_key" value="{{ old('operation_key',$operationKey) }}"><input type="hidden" name="expected_version" value="{{ old('expected_version',$item?->version ?? 0) }}">
<x-field name="title" :label="__('Название')" :value="old('title',$item?->title ?? '')" maxlength="120" required />
<div class="field"><label for="ingredients">{{ __('Ингредиенты') }}</label><textarea name="ingredients" id="ingredients" rows="3" maxlength="2000" required>{{ old('ingredients',$item?->ingredients ?? '') }}</textarea></div>
<x-field name="estimate" :label="__('Ориентир стоимости продуктов')" :value="old('estimate',$item?->estimate ?? '0.00')" inputmode="decimal" required />
<div class="field"><label for="currency">{{ __('Валюта') }}</label><select id="currency" name="currency">@foreach($currencies as $currency)<option value="{{ $currency->value }}" @selected(old('currency',$item?->currency->value ?? $defaultCurrency->value) === $currency->value)>{{ $currency->value }}</option>@endforeach</select></div>
<div class="field"><label for="kind">{{ __('Вид блюда') }}</label><select id="kind" name="kind">@foreach($kinds as $kind)<option value="{{ $kind->value }}" @selected(old('kind',$item?->kind->value ?? 'ordinary') === $kind->value)>{{ $kind->label() }}</option>@endforeach</select></div>
<x-field name="portions" :label="__('Количество порций')" type="number" :value="old('portions',$item?->portions ?? 1)" min="1" max="20" required />
<div data-dish-price><x-field name="heart_price" :label="__('Цена в сердечках')" type="number" :value="old('heart_price',$item?->heart_price ?? 0)" min="0" max="50" required /></div>
<p data-dish-free-note hidden>{{ __('Обычное блюдо: цена всегда 0 сердечек.') }}</p>
<p>{{ __('Обычное блюдо всегда бесплатно. Особое: 0 или от 5 до 50 сердечек; подсказки — 10, 20, 30. Ориентир продуктов не создаёт денежный долг.') }}</p>
@if($item?->photo_path)<x-product-photo :src="route('photos.dishes',$item)" :alt="$item->title" />@endif
<div class="field"><label for="photo">{{ __('Фото — если хотите') }}</label><input type="file" name="photo" id="photo" accept="image/jpeg,image/png,image/webp"><p>{{ __('JPEG, PNG или WebP, до 4 МБ. Фото видно только вам и партнёру; оставьте поле пустым, чтобы сохранить прежнее.') }}</p><p role="status" data-photo-status hidden></p><div data-photo-preview hidden><img class="product-photo" alt="{{ __('Предпросмотр выбранного фото') }}" data-photo-image></div></div>
<x-button type="submit">{{ __('Сохранить') }}</x-button>
</form>
</x-card><a class="button button--quiet section-space" href="{{ route('dishes.index') }}">{{ __('К каталогу') }}</a>
@endsection
