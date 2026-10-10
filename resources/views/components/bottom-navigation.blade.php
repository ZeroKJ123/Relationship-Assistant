@props(['couple' => null])
<nav class="bottom-navigation" aria-label="{{ __('Основная навигация') }}">
    <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif><span aria-hidden="true">⌂</span>{{ __('Главная') }}</a>
    @auth<a href="{{ $couple ? route('requests.index',$couple) : route('couples.index') }}" @if(request()->routeIs('requests.*')) aria-current="page" @endif><span aria-hidden="true">♧</span>{{ __('Просьбы') }}</a><a href="{{ route('profile.show') }}"><span aria-hidden="true">♙</span>{{ __('Профиль') }}</a>
    @else<a href="{{ route('login') }}"><span aria-hidden="true">♡</span>{{ __('Войти') }}</a><a href="{{ route('register') }}"><span aria-hidden="true">✦</span>{{ __('Начать') }}</a>@endauth
</nav>
