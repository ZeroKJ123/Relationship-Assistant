<?php

declare(strict_types=1);

it('shows the Russian interface without fabricated personal data', function (): void {
    $this->withoutVite()->get('/')
        ->assertOk()
        ->assertSee('lang="ru"', false)
        ->assertSee('Приложение в разработке')
        ->assertSee('Больше тепла.')
        ->assertDontSee('София')
        ->assertDontSee('Алексей');
});

it('renders associated field labels and accessible validation errors', function (): void {
    $this->blade('<x-field name="login" label="Логин" error="Введите логин" />')
        ->assertSee('for="login"', false)
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('aria-describedby="login-error"', false);
});

it('escapes user-provided avatar names', function (): void {
    $this->blade('<x-avatar :name="$name" />', ['name' => '<script>alert(1)</script>'])
        ->assertDontSee('<script>', false)
        ->assertSee('&lt;script&gt;', false);
});

it('renders accessible modal and tab relationships', function (): void {
    $this->withoutVite()->get('/')
        ->assertSee('aria-labelledby="about-dialog-title"', false)
        ->assertSee('aria-controls="panel-care"', false)
        ->assertSee('data-open-dialog="about-dialog"', false);
});

it('associates both field hints and errors without duplicate attributes', function (): void {
    $html = (string) $this->blade('<x-field name="login" label="Логин" error="Ошибка" aria-describedby="login-hint" />');
    expect(substr_count($html, 'aria-describedby='))->toBe(1)->and($html)->toContain('aria-describedby="login-hint login-error"');
});
