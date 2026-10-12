@props(['name', 'label'])
<div class="field"><label>{{ $label }}<select name="{{ $name }}" {{ $attributes }}>{{ $slot }}</select></label>@error($name)<p class="field-error">{{ $message }}</p>@enderror</div>
