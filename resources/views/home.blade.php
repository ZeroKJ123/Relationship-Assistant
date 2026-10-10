@extends('layouts.app')

@section('content')
    <section class="hero">
        <div class="hero-copy">
            <p class="eyebrow">{{ __('Для вас двоих') }}</p>
            <h1>{{ __('Больше тепла.') }}<br><em>{{ __('Каждый день.') }}</em></h1>
            <p class="hero-description">{{ __('Маленькие просьбы, общие планы и приятные моменты — всё, что делает вашу историю особенной.') }}</p>
            <a class="button button--primary" href="#together">{{ __('Что будем делать вместе') }} <span aria-hidden="true">↓</span></a>
        </div>
        <div class="hero-art" aria-hidden="true"><span class="sparkle sparkle--one">✦</span><span class="heart heart--large">♥</span><span class="heart heart--small">♥</span><span class="sparkle sparkle--two">✧</span><span class="hero-note">{{ __('Вы — одна команда') }}</span></div>
    </section>

    <section id="together" class="section-space" aria-labelledby="together-title">
        <p class="eyebrow">{{ __('Ваш маленький мир') }}</p>
        <h2 id="together-title">{{ __('Забота в простых вещах') }}</h2>
        <div class="feature-grid">
            <x-card tone="yellow"><span class="feature-icon" aria-hidden="true">♨</span><h3>{{ __('Кухня') }}</h3><p>{{ __('Выбирайте любимое блюдо и договаривайтесь об ужине.') }}</p></x-card>
            <x-card tone="purple"><span class="feature-icon" aria-hidden="true">☏</span><h3>{{ __('Просьбы') }}</h3><p>{{ __('Говорите о желаниях и находите условия, подходящие обоим.') }}</p></x-card>
            <x-card tone="pink"><span class="feature-icon" aria-hidden="true">♥</span><h3>{{ __('Свидания') }}</h3><p>{{ __('Находите время друг для друга. Даже среди обычных дел.') }}</p></x-card>
            <x-card tone="yellow"><span class="feature-icon" aria-hidden="true">✧</span><h3>{{ __('Виш-лист') }}</h3><p>{{ __('Сохраняйте мечты, чтобы радовать друг друга было проще.') }}</p></x-card>
        </div>
    </section>

    <x-card id="first-step" class="section-space start-card" aria-labelledby="start-title">
        <x-status>{{ __('Приложение в разработке') }}</x-status>
        <h2 id="start-title">{{ __('У вашей истории будет свой дом') }}</h2>
        <p>{{ __('Мы готовим личное пространство для вашей пары. Регистрация и создание пары уже доступны. Совместные действия появятся на следующих этапах.') }}</p>
        <div class="confirmation-actions">
            @auth<a class="button button--primary" href="{{ route('profile.show') }}">{{ __('Мой профиль') }}</a>
            @else<a class="button button--primary" href="{{ route('register') }}">{{ __('Создать аккаунт') }}</a><a class="button button--quiet" href="{{ route('login') }}">{{ __('Войти') }}</a>@endauth
        </div>
        <x-button variant="quiet" data-open-dialog="about-dialog">{{ __('Как это будет работать') }}</x-button>
        <noscript><p>{{ __('Создайте аккаунт, пригласите партнёра и согласуйте общие правила. Совместные дела появятся на следующих этапах.') }}</p></noscript>
    </x-card>

    <x-dialog id="about-dialog" :title="__('Вместе — с самого начала')">
        <x-tabs id="intro-tabs">
            <x-slot:triggers>
                <button id="intro-couple" role="tab" aria-controls="panel-couple" aria-selected="true" tabindex="0">{{ __('Пара') }}</button>
                <button id="intro-care" role="tab" aria-controls="panel-care" aria-selected="false" tabindex="-1">{{ __('Забота') }}</button>
            </x-slot:triggers>
            <div id="panel-couple" role="tabpanel" aria-labelledby="intro-couple" tabindex="0"><p>{{ __('Создайте аккаунт и пригласите партнёра по ссылке. Ваше пространство и история будут доступны только вам двоим.') }}</p></div>
            <div id="panel-care" role="tabpanel" aria-labelledby="intro-care" tabindex="0" hidden><p>{{ __('Договаривайтесь о просьбах, подтверждайте выполненные дела и благодарите друг друга сердечками. Обычная еда всегда бесплатна в сердечках.') }}</p></div>
        </x-tabs>
        <x-button data-close-dialog>{{ __('Понятно') }}</x-button>
    </x-dialog>
@endsection

@section('navigation')
    <x-bottom-navigation />
@endsection
