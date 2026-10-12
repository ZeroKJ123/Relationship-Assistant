@props(['name'])
@php($bounds = ['kitchen'=>'32 55 395 350','request'=>'464 94 348 292','date'=>'856 100 375 300','gift'=>'52 443 400 357','movies'=>'465 430 350 360','car'=>'842 488 395 310','rating'=>'52 850 365 350','home'=>'456 850 358 348','bell'=>'886 856 322 340'])
<svg class="illustration illustration--{{ $name }}" viewBox="{{ $bounds[$name] ?? $bounds['request'] }}" preserveAspectRatio="xMidYMid meet" aria-hidden="true" focusable="false"><image href="/images/love-icons-v1.png" width="1280" height="1280" /></svg>
