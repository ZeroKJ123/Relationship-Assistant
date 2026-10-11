@props(['couple' => null])
<nav class="bottom-navigation" aria-label="{{ __('Основная навигация') }}">
    <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif><x-illustration name="home" />{{ __('Главная') }}</a>
    @auth<a href="{{ $couple ? route('requests.index',$couple) : route('couples.index') }}" @if(request()->routeIs('requests.*')) aria-current="page" @endif><x-illustration name="bell" />{{ __('Просьбы') }}</a><a href="{{ route('profile.show') }}" @if(request()->routeIs('profile.*')) aria-current="page" @endif><x-avatar :name="auth()->user()->name" :src="auth()->user()->avatar_path ? route('profile.avatar',['v'=>auth()->user()->updated_at?->timestamp]) : null" />{{ __('Профиль') }}</a>
    @else<a href="{{ route('login') }}"><span aria-hidden="true">♡</span>{{ __('Войти') }}</a><a href="{{ route('register') }}"><span aria-hidden="true">✦</span>{{ __('Начать') }}</a>@endauth
</nav>
