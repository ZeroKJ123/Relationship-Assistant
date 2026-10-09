<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-950 font-sans text-slate-100 antialiased">
        <main class="mx-auto flex min-h-screen max-w-3xl flex-col justify-center px-6 py-16">
            <p class="mb-6 text-sm font-semibold uppercase tracking-widest text-emerald-400">Sail Starter</p>
            <h1 class="text-4xl font-semibold tracking-tight sm:text-5xl">Основа для нового проекта</h1>
            <p class="mt-6 max-w-xl text-lg leading-8 text-slate-400">Начните с первой страницы, добавьте свою логику и развивайте приложение.</p>
            <div class="mt-10 grid gap-4 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
                    <h2 class="text-sm text-slate-400">Приложение</h2>
                    <p class="mt-2 text-xl font-medium">Laravel {{ Illuminate\Foundation\Application::VERSION }}</p>
                    <p class="mt-2 text-sm text-slate-400">PHP {{ PHP_MAJOR_VERSION }}.{{ PHP_MINOR_VERSION }} · Laravel Sail</p>
                </div>
                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
                    <h2 class="text-sm text-slate-400">Интерфейс</h2>
                    <p class="mt-2 text-xl font-medium">Blade + Tailwind CSS</p>
                    <p class="mt-2 text-sm text-slate-400">Сборка ресурсов через Vite</p>
                </div>
            </div>
            <a href="https://laravel.com/docs/13.x" class="mt-10 w-fit text-sm font-medium text-emerald-400 underline underline-offset-4 hover:text-emerald-300">Документация Laravel →</a>
        </main>
    </body>
</html>
