@props(['item','isOwn','couple','wallet'])
<article class="dish-row product-card">
    <x-product-photo :src="$item->photo_path ? route('photos.dishes',$item) : null" :alt="$item->title" symbol="♨" />
    <div class="row__body">
        <h2 class="row__title">{{ $item->title }}</h2>
        <x-status :tone="$item->kind === \App\Enums\DishKind::Ordinary ? 'success' : 'neutral'">{{ $item->kind->label() }}</x-status>
        <span class="row__meta">{{ __('Примерные расходы') }}: ≈ {{ $item->estimate }} {{ $item->currency->value }} · {{ __('Порций: :count',['count'=>$item->portions]) }}</span>
    </div>
    <div class="dish-row__side">
        <span class="heart-price">{{ $item->heart_price === 0 ? __('Заказ: бесплатно') : __('Заказ: ♥ :amount',['amount'=>$item->heart_price]) }}</span>
        @if($isOwn)<a class="button button--quiet button--small" href="{{ route('dishes.edit',$item) }}">{{ __('Редактировать') }}</a>@endif
        @if(!$isOwn && $couple)
            @if($item->kind === \App\Enums\DishKind::Special && $wallet->balance < 0)<button class="button button--quiet button--small" disabled aria-describedby="dish-{{ $item->id }}-unavailable">{{ __('Особое блюдо недоступно') }}</button>
            @elseif($item->heart_price > 0 && $item->heart_price > $wallet->available)<button class="button button--quiet button--small" disabled aria-describedby="dish-{{ $item->id }}-unavailable">{{ __('Не хватает свободных сердечек') }}</button>
            @else<a class="button button--primary button--small" href="{{ route('requests.create',[$couple,'dish_id'=>$item->id]) }}">{{ __('Попросить приготовить') }}</a>@endif
        @endif
    </div>
    @if($item->ingredients)<p class="dish-row__note request-description">{{ $item->ingredients }}</p>@endif
    @if(!$isOwn && $couple)
        @if($item->kind === \App\Enums\DishKind::Special && $wallet->balance < 0)<p class="dish-row__note" id="dish-{{ $item->id }}-unavailable">{{ __('При минусе доступны обычные бесплатные блюда. Особые появятся, когда баланс станет неотрицательным.') }}</p>
        @elseif($item->heart_price > 0 && $item->heart_price > $wallet->available)<p class="dish-row__note" id="dish-{{ $item->id }}-unavailable">{{ __('Нужно :price, свободно :available. Часть баланса может быть в резерве.', ['price'=>$item->heart_price,'available'=>$wallet->available]) }}</p>@endif
    @endif
</article>
