@props(['name', 'src' => null])
<span {{ $attributes->class(['avatar']) }}>
    @if($src)<img src="{{ $src }}" alt="{{ $name }}" width="80" height="80">
    @else<span aria-label="{{ $name }}">{{ mb_substr($name, 0, 1) }}</span>@endif
</span>
