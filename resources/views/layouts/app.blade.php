<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#eee6ff">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/heart-v1-180.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ __('Наша пара') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Наша пара'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-authenticated="{{ auth()->check() ? 'true' : 'false' }}">
    <a class="skip-link" href="#main-content">{{ __('Перейти к содержимому') }}</a>
    <div class="app-shell">
        <header class="app-header">
            <a class="brand" href="{{ route('home') }}"><span aria-hidden="true">♥</span> {{ __('Наша пара') }}</a>
            @auth<a class="pill" href="{{ route('couples.index') }}">{{ __('Мои пары') }}</a>@else<span class="pill">{{ __('Начало истории') }}</span>@endauth
        </header>
        <p class="connection-status" role="status" tabindex="-1" data-connection-status hidden>{{ __('Нет соединения. Действия требуют интернета. Если связь пропала после отправки, проверьте результат после подключения.') }}</p>
        <main id="main-content" tabindex="-1">@yield('content')</main>
        @yield('navigation')
    </div>
</body>
</html>
