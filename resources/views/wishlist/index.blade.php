@extends('layouts.app')
@section('title', __('Виш-лист'))
@section('content')
<h1>{{ __('Виш-лист') }} · {{ $owner->name }}</h1>
<p>{{ __('Добавляйте желания вручную. Подарок по условию подтверждает его получатель.') }}</p>
<nav class="confirmation-actions section-space" aria-label="{{ __('Чей каталог') }}"><a class="button {{ $isOwn ? 'button--primary' : 'button--quiet' }}" href="{{ route('wishlist.index') }}" @if($isOwn) aria-current="page" @endif>{{ __('Мои') }}</a>@if($partner)<a class="button {{ !$isOwn ? 'button--primary' : 'button--quiet' }}" href="{{ route('wishlist.index',['owner_id'=>$partner->id]) }}" @if(!$isOwn) aria-current="page" @endif>{{ __('Партнёра') }}</a>@endif</nav>
<nav class="confirmation-actions section-space" aria-label="{{ __('Статус желания') }}"><a class="pill" href="{{ route('wishlist.index',['owner_id'=>$owner->id]) }}">{{ __('Желаю') }}</a><a class="pill" href="{{ route('wishlist.index',['owner_id'=>$owner->id,'archive'=>1]) }}">{{ __('Архив') }}</a></nav>
<x-form-feedback />
<div class="product-grid section-space">
@forelse($items as $item)
<x-card class="product-card form-stack">
<x-product-photo :src="$item->photo_path ? route('photos.wishlist',$item) : null" :alt="$item->title"  />
<h2>{{ $item->title }}</h2>
<span class="price-chip">{{ $item->price }} {{ $item->currency->value }}</span>@if($item->description)<p class="request-description">{{ $item->description }}</p>@endif
@if($item->received_at)<x-status>{{ __('Подарок получен') }}</x-status>@elseif($item->claimed_condition_id)<x-status>{{ __('Подарок обещан') }}</x-status>@elseif($item->archived_at)<x-status>{{ __('В архиве') }}</x-status>@endif
@if($isOwn && !$item->archived_at)<a class="button button--quiet" href="{{ route('wishlist.edit',$item) }}">{{ __('Редактировать') }}</a>@endif
@if($isOwn && !$item->archived_at && !$item->claimed_condition_id)<form method="post" action="{{ route('wishlist.archive',$item) }}" class="form-stack" data-submit-lock>@csrf<input type="hidden" name="expected_version" value="{{ $item->version }}"><label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю перенос в архив') }}</label><x-button type="submit" variant="quiet">{{ __('В архив') }}</x-button></form>@endif
</x-card>
@empty<x-card><p>{{ __('Здесь пока нет карточек.') }}</p></x-card>@endforelse
</div>
{{ $items->links() }}
@if($isOwn)<a class="button button--primary section-space" href="{{ route('wishlist.create') }}">{{ __('Добавить желание') }}</a>@endif
@if($couple)<a class="button button--quiet section-space" href="{{ route('couples.show',$couple) }}">{{ __('К нашей паре') }}</a>@endif
@endsection
