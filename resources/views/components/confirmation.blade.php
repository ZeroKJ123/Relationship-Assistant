@props(['id', 'title', 'form', 'label'])
<x-dialog :id="$id" :title="$title">
    <p>{{ $slot }}</p>
    <div class="confirmation-actions">
        <x-button variant="quiet" data-close-dialog>{{ __('Отмена') }}</x-button>
        <x-button :data-confirm-form="$form">{{ $label }}</x-button>
    </div>
</x-dialog>
