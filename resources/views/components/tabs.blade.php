@props(['id'])
<div id="{{ $id }}" data-tabs>
    <div class="tabs" role="tablist" aria-label="{{ __('Возможности приложения') }}">{{ $triggers }}</div>
    {{ $slot }}
</div>
