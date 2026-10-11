@props(['entry'])
@if($entry->sticker_status === \App\Enums\MoodStickerStatus::Ready)
    @if($entry->sticker_format === \App\Enums\MoodStickerFormat::Animation)
        <span class="mood-sticker" data-mood-animation="{{ route('mood.sticker',$entry) }}" role="img" aria-label="{{ __('Стикер настроения') }}"></span>
    @elseif($entry->sticker_format === \App\Enums\MoodStickerFormat::Video)
        <video class="mood-sticker" src="{{ route('mood.sticker',$entry) }}" autoplay loop muted playsinline aria-label="{{ __('Стикер настроения') }}"></video>
    @else<img class="mood-sticker" src="{{ route('mood.sticker',$entry) }}" alt="{{ __('Стикер настроения') }}">@endif
@elseif($entry->sticker_status === \App\Enums\MoodStickerStatus::Pending)<span class="field-hint">{{ __('Стикер загружается…') }}</span>
@elseif($entry->sticker_status === \App\Enums\MoodStickerStatus::Failed)<span class="field-hint">{{ __('Стикер не удалось загрузить') }}</span>
@elseif($entry->emoji)<span class="partner-mood__emoji" aria-label="{{ __('Эмоджи настроения') }}">{{ $entry->emoji }}</span>@endif
