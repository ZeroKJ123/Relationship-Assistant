@extends('layouts.app')
@section('title', __('Магазин наград'))
@section('content')
<div class="page-head"><h1 class="screen-title">{{ __('Магазин наград') }} · {{ $owner->name }}</h1>@if($isOwn)<a class="button button--primary button--small" href="{{ route('rewards.create') }}">+ {{ __('Добавить награду') }}</a>@else<a class="price-chip" href="{{ route('hearts.index') }}">♥ {{ $wallet->balance }}</a>@endif</div>
<p class="lead">{{ $isOwn ? __('Здесь то, что вы готовы сделать для партнёра за сердечки. Цену назначаете вы.') : __('Свободно: :amount · резерв: :reserved', ['amount'=>$wallet->available,'reserved'=>$wallet->reserved]) }}</p>
<p class="field-hint">{{ __('Покупка создаёт обычную платную просьбу: партнёр соглашается, сердечки резервируются и списываются после проверки выполнения.') }}</p>
<nav class="catalog-tabs section-space" aria-label="{{ __('Чей магазин') }}"><a href="{{ route('rewards.index') }}" @if($isOwn) aria-current="page" @endif>{{ __('Мои') }}</a>@if($partner)<a href="{{ route('rewards.index',['owner_id'=>$partner->id]) }}" @if(!$isOwn) aria-current="page" @endif>{{ __('Партнёра') }}</a>@endif</nav>
<x-form-feedback />
@forelse($rewards as $reward)
<article class="row reward-row">
    <x-icon name="gift" />
    <div class="row__body"><h2 class="row__title">{{ $reward->title }}</h2>@if($reward->description)<span class="row__meta request-description">{{ $reward->description }}</span>@endif<span class="heart-price">♥ {{ $reward->heart_price }}</span></div>
    @if($isOwn)<a class="button button--quiet button--small" href="{{ route('rewards.edit',$reward) }}">{{ __('Изменить') }}</a>
    @elseif($couple && $reward->heart_price > $wallet->available)<button class="button button--quiet button--small" disabled aria-describedby="reward-{{ $reward->id }}-unavailable">{{ __('Не хватает') }}</button>
    @elseif($couple)<a class="button button--primary button--small" href="{{ route('requests.create',[$couple,'reward_id'=>$reward->id]) }}">{{ __('Купить') }}</a>@endif
</article>
@if(!$isOwn && $couple && $reward->heart_price > $wallet->available)<p class="field-hint" id="reward-{{ $reward->id }}-unavailable">{{ __('Нужно :price, свободно :available. Часть баланса может быть в резерве.', ['price'=>$reward->heart_price,'available'=>$wallet->available]) }}</p>@endif
@empty<p class="row row--empty">{{ $isOwn ? __('Добавьте первую награду: например, «Массаж — 30 сердечек».') : __('Партнёр ещё не добавил награды.') }}</p>@endforelse
{{ $rewards->links() }}
@endsection

@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
