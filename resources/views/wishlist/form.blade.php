@extends('layouts.app')
@section('title', __('Желание'))
@section('content')
<x-card class="auth-card">
<h1 class="form-title">{{ $item ? __('Редактировать карточку') : __('Новая карточка') }}</h1>
<p>{{ __('Добавьте название и примерную цену. Покупку и передачу подарка вы организуете самостоятельно.') }}</p><x-form-feedback />
<form method="post" enctype="multipart/form-data" action="{{ $item ? route('wishlist.update',$item) : route('wishlist.store') }}" class="form-stack" data-submit-lock data-wishlist-editor data-photo-invalid="{{ __('Выберите JPEG, PNG или WebP до 4 МБ.') }}" data-photo-checking="{{ __('Проверяем фото…') }}" data-photo-ready="{{ __('Фото выбрано. Сохраните карточку, чтобы загрузить его.') }}" data-photo-failed="{{ __('Не удалось прочитать фото. Выберите другой файл.') }}">
@csrf @if($item) @method('put') @endif
<input type="hidden" name="operation_key" value="{{ old('operation_key',$operationKey) }}"><input type="hidden" name="expected_version" value="{{ old('expected_version',$item?->version ?? 0) }}">
<x-field name="title" :label="__('Название')" :value="old('title',$item?->title ?? '')" maxlength="120" required />
<x-field name="description" :label="__('Ссылка на товар — если есть')" :value="old('description',$item?->description ?? '')" :error="$errors->first('description')" maxlength="2000" inputmode="url" autocapitalize="off" placeholder="https://" />
<x-field name="price" :label="__('Ориентир цены')" :value="old('price',$item?->price ?? '0.00')" inputmode="decimal" required />
<div class="field"><label for="currency">{{ __('Валюта') }}</label><select id="currency" name="currency">@foreach($currencies as $currency)<option value="{{ $currency->value }}" @selected(old('currency',$item?->currency->value ?? $defaultCurrency->value) === $currency->value)>{{ $currency->value }}</option>@endforeach</select></div>

@if($item?->photo_path)<x-product-photo :src="route('photos.wishlist',$item)" :alt="$item->title" />@endif
<x-photo-field />
<x-button type="submit">{{ __('Сохранить') }}</x-button>
</form>
</x-card><a class="button button--quiet section-space" href="{{ route('wishlist.index') }}">{{ __('К каталогу') }}</a>
@endsection
