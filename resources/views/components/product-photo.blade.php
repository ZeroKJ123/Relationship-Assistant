@props(['src'=>null,'alt','symbol'=>'♥'])
@if($src)<img class="product-photo" src="{{ $src }}" alt="{{ $alt }}" loading="lazy" width="600" height="400">
@else<div class="product-photo product-photo--empty" role="img" aria-label="{{ __('Фото не добавлено') }}"><span aria-hidden="true">{{ $symbol }}</span><span>{{ __('Фото не добавлено') }}</span></div>@endif
