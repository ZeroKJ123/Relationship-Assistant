@props(['item','isOwn','couple','wallet'])
<x-card class="product-card dish-card">
    <x-product-photo :src="$item->photo_path ? route('photos.dishes',$item) : null" :alt="$item->title" symbol="♨" />
    <div class="dish-details"><div><h2>{{ $item->title }}</h2><x-status :tone="$item->kind === \App\Enums\DishKind::Ordinary ? 'success' : 'neutral'">{{ $item->kind->label() }}</x-status><p class="request-description">{{ $item->ingredients }}</p><p>{{ __('Порций: :count',['count'=>$item->portions]) }}</p></div>
        <div class="dish-prices"><p>{{ __('Примерные расходы') }}</p><span class="price-chip">≈ {{ $item->estimate }} {{ $item->currency->value }}</span><p class="heart-price">{{ $item->heart_price === 0 ? __('Заказ: бесплатно') : __('Заказ: ♥ :amount',['amount'=>$item->heart_price]) }}</p></div>
    </div>
    @if(!$isOwn && $couple)
        @if($item->kind === \App\Enums\DishKind::Special && $wallet->balance < 0)<button class="button button--quiet" disabled aria-describedby="dish-{{ $item->id }}-unavailable">{{ __('Особое блюдо недоступно') }}</button><p id="dish-{{ $item->id }}-unavailable">{{ __('При минусе доступны обычные бесплатные блюда. Особые появятся, когда баланс станет неотрицательным.') }}</p>
        @elseif($item->heart_price > 0 && $item->heart_price > $wallet->available)<button class="button button--quiet" disabled aria-describedby="dish-{{ $item->id }}-unavailable">{{ __('Не хватает свободных сердечек') }}</button><p id="dish-{{ $item->id }}-unavailable">{{ __('Нужно :price, свободно :available. Часть баланса может быть в резерве.', ['price'=>$item->heart_price,'available'=>$wallet->available]) }}</p>
        @else<a class="button {{ $item->kind === \App\Enums\DishKind::Ordinary ? 'button--coral' : 'button--primary' }}" href="{{ route('requests.create',[$couple,'dish_id'=>$item->id]) }}">{{ __('Попросить приготовить') }}</a>@endif
    @endif
    @if($isOwn)<a class="button button--quiet" href="{{ route('dishes.edit',$item) }}">{{ __('Редактировать') }}</a>@endif
</x-card>
