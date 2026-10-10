@props(['name', 'label', 'value' => '', 'error' => null, 'hint' => null])
<div class="field">
    <label for="{{ $name }}">{{ $label }}</label>
    <textarea id="{{ $name }}" name="{{ $name }}" {{ $attributes->merge(['rows' => 3, 'aria-invalid' => $error ? 'true' : null, 'aria-describedby' => trim(($hint ? $name.'-hint ' : '').($error ? $name.'-error' : '')) ?: null]) }}>{{ $value }}</textarea>
    @if($hint)<p class="field-hint" id="{{ $name }}-hint">{{ $hint }}</p>@endif
    @if($error)<p class="field-error" id="{{ $name }}-error">{{ $error }}</p>@endif
</div>
