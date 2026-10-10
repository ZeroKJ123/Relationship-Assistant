@extends('layouts.app')
@section('title', __('Кухня'))
@section('content')
<div class="section-heading"><h1 class="screen-title">{{ __('Кухня') }} · {{ $owner->name }}</h1><a class="price-chip" href="{{ route('hearts.index') }}">♥ {{ $wallet->balance }}</a></div><p>{{ __('Свободно: :amount · резерв: :reserved', ['amount'=>$wallet->available,'reserved'=>$wallet->reserved]) }}</p>
<p>{{ __('Обычные блюда бесплатны; цена продуктов — ориентир, а не перевод денег.') }}</p>
<nav class="catalog-tabs section-space" aria-label="{{ __('Чей каталог') }}"><a class="button {{ $isOwn ? 'button--primary' : 'button--quiet' }}" href="{{ route('dishes.index') }}" @if($isOwn) aria-current="page" @endif>{{ __('Мои') }}</a>@if($partner)<a class="button {{ !$isOwn ? 'button--primary' : 'button--quiet' }}" href="{{ route('dishes.index',['owner_id'=>$partner->id]) }}" @if(!$isOwn) aria-current="page" @endif>{{ __('Партнёра') }}</a>@endif</nav>
<nav class="confirmation-actions section-space" aria-label="{{ __('Вид блюда') }}"><a class="filter-chip" href="{{ route('dishes.index',['owner_id'=>$owner->id]) }}" @if(!$filter) aria-current="page" @endif>{{ __('Все') }}</a>@foreach($kinds as $kind)<a class="filter-chip" href="{{ route('dishes.index',['owner_id'=>$owner->id,'kind'=>$kind->value]) }}" @if($filter === $kind) aria-current="page" @endif>{{ $kind->label() }}</a>@endforeach</nav>
<x-form-feedback />
<div class="product-grid section-space">
@forelse($items as $item)
<x-dish-card :item="$item" :is-own="$isOwn" :couple="$couple" :wallet="$wallet" />
@empty<x-card class="form-stack"><h2>{{ $filter ? __('Таких блюд пока нет') : __('Кухня ждёт первых рецептов') }}</h2><p>{{ $isOwn ? __('Добавьте любимое блюдо, состав и примерные расходы на продукты.') : __('Партнёр ещё не добавил блюда в этот раздел.') }}</p>@if($filter)<a href="{{ route('dishes.index',['owner_id'=>$owner->id]) }}">{{ __('Показать все блюда') }}</a>@endif</x-card>@endforelse
</div>
{{ $items->links() }}
@if($isOwn)<a class="button button--primary section-space" href="{{ route('dishes.create') }}">{{ __('Добавить блюдо') }}</a>@endif
@if($couple)<a class="button button--quiet section-space" href="{{ route('couples.show',$couple) }}">{{ __('К нашей паре') }}</a>@endif
@endsection

@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
