@props(['name', 'label', 'type' => 'text', 'value' => '', 'error' => null])
<div class="field">
    <label for="{{ $name }}">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value }}"
        @if($error) aria-invalid="true" @endif
        {{ $attributes->except('aria-describedby')->merge(['aria-describedby' => trim(sprintf('%s %s', $attributes->get('aria-describedby', ''), $error ? sprintf('%s-error', $name) : ''))]) }}>
    @if($error)<p class="field-error" id="{{ $name }}-error">{{ $error }}</p>@endif
</div>
