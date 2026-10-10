@extends('layouts.app')
@section('title', __('Виш-лист'))
@section('content')
<h1 class="screen-title">{{ __('Виш-лист') }} · {{ $owner->name }}</h1>
<p>{{ __('Добавляйте желания вручную. Подарок по условию подтверждает его получатель.') }}</p>
<nav class="catalog-tabs section-space" aria-label="{{ __('Чей каталог') }}"><a class="button {{ $isOwn ? 'button--primary' : 'button--quiet' }}" href="{{ route('wishlist.index') }}" @if($isOwn) aria-current="page" @endif>{{ __('Мои') }}</a>@if($partner)<a class="button {{ !$isOwn ? 'button--primary' : 'button--quiet' }}" href="{{ route('wishlist.index',['owner_id'=>$partner->id]) }}" @if(!$isOwn) aria-current="page" @endif>{{ __('Партнёра') }}</a>@endif</nav>
<nav class="confirmation-actions section-space" aria-label="{{ __('Статус желания') }}"><a class="filter-chip" href="{{ route('wishlist.index',['owner_id'=>$owner->id]) }}" @if(!$isArchive) aria-current="page" @endif>{{ __('Желаю') }}</a><a class="filter-chip" href="{{ route('wishlist.index',['owner_id'=>$owner->id,'archive'=>1]) }}" @if($isArchive) aria-current="page" @endif>{{ __('Архив') }}</a></nav>
<x-form-feedback />
<div class="wishlist-grid section-space">
@forelse($items as $item)
<x-wishlist-card :item="$item" :is-own="$isOwn" :couple="$couple" />
@empty<x-card><h2>{{ $isArchive ? __('В архиве пока пусто') : __('Что вас порадует?') }}</h2><p>{{ $isOwn ? __('Добавьте желание вручную: название, примерную цену и фото, если хотите.') : __('Партнёр ещё не добавил желания в этот раздел.') }}</p></x-card>@endforelse
</div>
{{ $items->links() }}
@if($isOwn)<a class="button button--primary section-space" href="{{ route('wishlist.create') }}">{{ __('Добавить желание') }}</a>@endif
@if($couple)<a class="button button--quiet section-space" href="{{ route('couples.show',$couple) }}">{{ __('К нашей паре') }}</a>@endif
@endsection

@section('navigation')<x-bottom-navigation :couple="$couple" />@endsection
