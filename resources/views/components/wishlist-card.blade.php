@props(['item','isOwn','couple'])
<x-card class="wishlist-card">
    <div class="wishlist-card-main"><x-product-photo :src="$item->photo_path ? route('photos.wishlist',$item) : null" :alt="$item->title" />
        <div class="form-stack"><h2>{{ $item->title }}</h2><span class="price-chip">{{ $item->price }} {{ $item->currency->value }}</span>@if($item->description)<p class="request-description">{{ $item->description }}</p>@endif
        @if($item->received_at)<x-status tone="success">{{ __('Подарок получен') }}</x-status>@elseif($item->claimed_condition_id)<x-status>{{ __('Подарок обещан') }}</x-status>@elseif($item->archived_at)<x-status>{{ __('В архиве') }}</x-status>@else<x-status>{{ __('Хочу в подарок') }}</x-status>@endif
        @if($isOwn && !$item->archived_at)<a class="button button--quiet" href="{{ route('wishlist.edit',$item) }}">{{ __('Редактировать') }}</a>@endif</div>
    </div>
    @if(!$item->archived_at && !$item->claimed_condition_id)<div class="wishlist-condition"><span aria-hidden="true">♧</span><p>{{ $isOwn ? __('Можно выбрать как условие входящей просьбы. Партнёр примет его отдельно.') : __('Партнёр может выбрать это желание как условие вашей просьбы.') }}</p>@if($isOwn && $couple)<a href="{{ route('requests.index',$couple) }}">{{ __('Открыть просьбы') }}</a>@endif</div>@endif
    @if($item->claimed_condition_id)<p>{{ __('Желание уже обещано. Получение подтверждает получатель; перенос в архив пока недоступен.') }}</p>@endif
    @if($isOwn && !$item->archived_at && !$item->claimed_condition_id)<details><summary>{{ __('Перенести в архив') }}</summary><form method="post" action="{{ route('wishlist.archive',$item) }}" class="form-stack" data-submit-lock>@csrf<input type="hidden" name="expected_version" value="{{ $item->version }}"><label class="confirmation-check"><input type="checkbox" name="confirmation" value="1" required>{{ __('Подтверждаю перенос в архив') }}</label><x-button type="submit" variant="quiet">{{ __('В архив') }}</x-button></form></details>@endif
</x-card>
