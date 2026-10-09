@props(['tone' => 'white'])
<section {{ $attributes->class(['card', 'card--'.$tone]) }}>{{ $slot }}</section>
