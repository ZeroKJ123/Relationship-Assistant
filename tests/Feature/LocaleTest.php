<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Models\User;
use App\Services\Localization\LocalePreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Finder\Finder;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('shows guest pages in English by default and in the language chosen on this device', function (): void {
    config(['app.locale' => 'en']);
    $this->get(route('login'))->assertOk()->assertSee('lang="en"', false)->assertSee('Sign in')->assertDontSee('Войти');

    $this->from(route('login'))->patch(route('locale.update'), ['locale' => 'ru'])->assertRedirect(route('login'))->assertCookie(LocalePreference::COOKIE, 'ru');
    $this->withCookie(LocalePreference::COOKIE, 'ru')->get(route('login'))->assertOk()->assertSee('lang="ru"', false)->assertSee('Войти');
    $this->withCookie(LocalePreference::COOKIE, 'de')->get(route('login'))->assertOk()->assertSee('lang="en"', false);
});

it('lets a user switch the interface language in the profile and rejects unknown languages', function (): void {
    $user = User::factory()->create(['locale' => Locale::Ru]);
    $this->asUser($user)->get(route('profile.show'))->assertOk()->assertSee('Язык интерфейса')->assertSee('Мой профиль');

    $this->patch(route('locale.update'), ['locale' => 'de'])->assertSessionHasErrors('locale');
    expect($user->refresh()->locale)->toBe(Locale::Ru);

    $this->patch(route('locale.update'), ['locale' => 'en'])->assertRedirect(route('profile.show'))->assertSessionHas('status', 'Language saved.')->assertCookie(LocalePreference::COOKIE, 'en');
    expect($user->refresh()->locale)->toBe(Locale::En);
    $this->get(route('profile.show'))->assertOk()->assertSee('lang="en"', false)->assertSee('Interface language')->assertSee('My profile')->assertDontSee('Мой профиль');
});

it('keeps each user in their own language regardless of the device cookie', function (): void {
    $english = User::factory()->create(['locale' => Locale::En]);
    $this->asUser($english)->withCookie(LocalePreference::COOKIE, 'ru')->get(route('profile.show'))->assertOk()->assertSee('My profile')->assertCookie(LocalePreference::COOKIE, 'en');
});

it('renders texts for another person in their language and restores the current one', function (): void {
    $english = User::factory()->create(['locale' => Locale::En]);
    $preference = app(LocalePreference::class);

    expect($preference->runFor($english, fn (): string => __('Новая просьба')))->toBe('New request')
        ->and($preference->runFor($english, fn (): string => now()->startOfYear()->isoFormat('MMMM')))->toBe('January')
        ->and(app()->getLocale())->toBe('ru')
        ->and(now()->startOfYear()->isoFormat('MMMM'))->toBe('январь')
        ->and(__('Новая просьба'))->toBe('Новая просьба');
    expect(fn () => $preference->runFor($english, fn () => throw new RuntimeException('failed')))->toThrow(RuntimeException::class);
    expect(app()->getLocale())->toBe('ru');
});

it('has an English text with the same placeholders for every interface string', function (): void {
    $source = file_get_contents(lang_path('en.json'));
    assert(is_string($source));
    /** @var array<string, string> $english */
    $english = json_decode($source, associative: true, flags: JSON_THROW_ON_ERROR);
    $missing = [];
    foreach ((new Finder)->files()->in([app_path(), resource_path('views')])->name('*.php') as $file) {
        preg_match_all('/(?:__|@lang)\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'/u', $file->getContents(), $matches);
        foreach ($matches[1] as $literal) {
            $key = stripslashes($literal);
            if (! array_key_exists($key, $english)) {
                $missing[] = sprintf('%s: %s', $file->getRelativePathname(), $key);
            }
        }
    }
    expect($missing)->toBe([]);

    $placeholders = function (string $text): array {
        preg_match_all('/:[a-z_]+/', $text, $found);
        sort($found[0]);

        return $found[0];
    };
    $withoutPlaceholderKeys = ['Укажите время в формате часы:минуты.'];
    foreach ($english as $key => $translation) {
        expect($translation)->not->toBe('');
        if (! in_array($key, $withoutPlaceholderKeys, true)) {
            expect($placeholders($translation))->toBe($placeholders($key), $key);
        }
    }
});
