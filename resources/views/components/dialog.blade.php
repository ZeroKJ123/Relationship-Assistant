@props(['id', 'title'])
<dialog id="{{ $id }}" class="sheet" aria-labelledby="{{ $id }}-title">
    <div class="sheet-heading">
        <h2 id="{{ $id }}-title">{{ $title }}</h2>
        <x-button variant="quiet" data-close-dialog aria-label="{{ __('Закрыть') }}">×</x-button>
    </div>
    {{ $slot }}
</dialog>
