<!DOCTYPE html>
<html style="{{ $appearance->css() }}" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ $appearance->theme()['background'] }}">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/heart-v1-180.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ __('Наша пара') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth @if(config('realtime.enabled'))
    <meta name="realtime-key" content="{{ config('broadcasting.connections.reverb.key') }}">
    <meta name="realtime-port" content="{{ config('realtime.public_port') }}">
    @endif @endauth
    <title>@yield('title', __('Наша пара'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-decoration="{{ $appearance->theme()['decoration'] }}" data-user-id="{{ auth()->id() }}" data-authenticated="{{ auth()->check() ? 'true' : 'false' }}">
    <a class="skip-link" href="#main-content">{{ __('Перейти к содержимому') }}</a>
    <div class="app-shell">
        <header class="app-header">
            @if(auth()->check() && ! request()->routeIs('home'))<a class="app-back" href="@yield('back', route('home'))"><x-icon name="back" />@yield('back-label', __('Главная'))</a>@endif
            <a class="brand" href="{{ route('home') }}"><x-icon name="heart" /> {{ __('Наша пара') }}</a>
            @guest<span class="pill">{{ __('Начало истории') }}</span>@endguest
        </header>
        <p class="connection-status" role="status" tabindex="-1" data-connection-status hidden>{{ __('Нет соединения. Действия требуют интернета. Если связь пропала после отправки, проверьте результат после подключения.') }}</p>
        @if(request()->attributes->has('admin_actor_id'))<aside class="connection-status" role="status">{{ __('Проверка от имени :name. Действия меняют реальные данные и отправляют уведомления.', ['name'=>auth()->user()->name]) }}<form method="post" action="{{ route('admin.panel.stop') }}">@csrf<x-button type="submit" variant="quiet">{{ __('Вернуться в админ-панель') }}</x-button></form></aside>@endif
        <main id="main-content" tabindex="-1">@yield('content')</main>
        @yield('navigation')
    </div>
    <div class="toast" role="status" aria-live="polite" hidden data-toast @if(session('toast')) data-initial-message="{{ session('toast') }}" @endif></div>
</body>
</html>
