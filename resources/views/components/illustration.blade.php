@props(['name'])
@php($icon = $appearance->icon($name))
@if($icon['kind'] === 'image')
<img class="illustration illustration--{{ $name }}" src="{{ route('appearance.asset', ['asset'=>$icon['value']]) }}" alt="" aria-hidden="true">
@elseif($icon['kind'] === 'glyph')
<span class="illustration illustration--glyph illustration--{{ $name }}" aria-hidden="true">{{ $icon['value'] }}</span>
@else
<x-icon :name="$name" class="illustration illustration--{{ $name }}" />
@endif
