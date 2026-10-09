@props(['variant' => 'primary', 'type' => 'button'])
<button type="{{ $type }}" {{ $attributes->class(['button', 'button--'.$variant]) }}>{{ $slot }}</button>
