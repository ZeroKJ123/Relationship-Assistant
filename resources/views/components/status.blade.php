@props(['tone' => 'neutral'])
<span {{ $attributes->class(['status', 'status--'.$tone]) }}>{{ $slot }}</span>
