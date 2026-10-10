@extends('layouts.app')
@section('title', __('Кухня'))
@section('content')
<h1>{{ __('Кухня') }} · {{ $owner->name }}</h1>
<p>{{ __('Обычные блюда бесплатны; цена продуктов — ориентир, а не перевод денег.') }}</p>
<nav class="confirmation-actions section-space" aria-label="{{ __('Чей каталог') }}"><a class="button {{ $isOwn ? 'button--primary' : 'button--quiet' }}" href="{{ route('dishes.index') }}" @if($isOwn) aria-current="page" @endif>{{ __('Мои') }}</a>@if($partner)<a class="button {{ !$isOwn ? 'button--primary' : 'button--quiet' }}" href="{{ route('dishes.index',['owner_id'=>$partner->id]) }}" @if(!$isOwn) aria-current="page" @endif>{{ __('Партнёра') }}</a>@endif</nav>
<nav class="confirmation-actions section-space" aria-label="{{ __('Вид блюда') }}"><a class="pill" href="{{ route('dishes.index',['owner_id'=>$owner->id]) }}">{{ __('Все') }}</a>@foreach($kinds as $kind)<a class="pill" href="{{ route('dishes.index',['owner_id'=>$owner->id,'kind'=>$kind->value]) }}" @if($filter === $kind) aria-current="page" @endif>{{ $kind->label() }}</a>@endforeach</nav>
<x-form-feedback />
<div class="product-grid section-space">
@forelse($items as $item)
<x-card class="product-card form-stack">
<x-product-photo :src="$item->photo_path ? route('photos.dishes',$item) : null" :alt="$item->title" symbol="♨" />
<h2>{{ $item->title }}</h2>
<x-status>{{ $item->kind->label() }}</x-status><p class="request-description">{{ $item->ingredients }}</p><p>{{ __('Порций: :count',['count'=>$item->portions]) }}</p><span class="price-chip">{{ __('Продукты ≈ :amount :currency',['amount'=>$item->estimate,'currency'=>$item->currency->value]) }}</span><p class="heart-price">{{ $item->heart_price === 0 ? __('Бесплатно') : __('♥ :amount сердечек',['amount'=>$item->heart_price]) }}</p>
@if(!$isOwn && $couple)<a class="button button--primary" href="{{ route('requests.create',[$couple,'dish_id'=>$item->id]) }}">{{ __('Хочу это блюдо') }}</a>@endif
@if($isOwn)<a class="button button--quiet" href="{{ route('dishes.edit',$item) }}">{{ __('Редактировать') }}</a>@endif

</x-card>
@empty<x-card><p>{{ __('Здесь пока нет карточек.') }}</p></x-card>@endforelse
</div>
{{ $items->links() }}
@if($isOwn)<a class="button button--primary section-space" href="{{ route('dishes.create') }}">{{ __('Добавить блюдо') }}</a>@endif
@if($couple)<a class="button button--quiet section-space" href="{{ route('couples.show',$couple) }}">{{ __('К нашей паре') }}</a>@endif
@endsection
