<x-card class="auth-card" data-pwa-help data-live-preserve="pwa-help">
    <h2>{{ __('Приложение на телефоне') }}</h2>
    <p>{{ __('Добавьте «Нашу пару» на главный экран, чтобы открывать её как приложение.') }}</p>
    <x-button type="button" class="section-space" data-pwa-install hidden>{{ __('Установить приложение') }}</x-button>
    <p role="status" class="field-hint" data-pwa-status data-accepted="{{ __('Установка запрошена. Проверьте главный экран телефона.') }}" data-dismissed="{{ __('Установить можно позже через меню браузера.') }}" data-failed="{{ __('Не удалось открыть установку. Используйте меню браузера.') }}"></p>
    <details class="settings-history section-space">
        <summary>{{ __('Как установить') }}</summary>
        <div class="form-stack">
            <p><strong>{{ __('iPhone / iPad') }}</strong>: {{ __('Откройте сайт в Safari, выберите «Поделиться» → «На экран Домой» и подтвердите добавление.') }}</p>
            <p><strong>{{ __('Android') }}</strong>: {{ __('Откройте сайт в Chrome, выберите «Установить приложение» или «Добавить на главный экран» в меню браузера.') }}</p>
            <p>{{ __('Установка доступна на защищённом HTTPS-сайте и зависит от браузера. Личные страницы и действия требуют интернета.') }}</p>
        </div>
    </details>
</x-card>
