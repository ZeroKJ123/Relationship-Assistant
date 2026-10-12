<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps private pages uncached while providing manifest icons and offline guidance', function (): void {
    $user = User::factory()->create();
    $this->withoutVite()->asUser($user)->get(route('profile.show'))->assertOk()
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
        ->assertSee('rel="manifest" href="/manifest.webmanifest"', escape: false)
        ->assertSee('rel="apple-touch-icon"', escape: false)
        ->assertSee('data-authenticated="true"', escape: false)
        ->assertDontSee('Как установить')
        ->assertSee('data-connection-status', escape: false);
});

it('provides correctly sized installation icons and a public launch URL without account identifiers', function (): void {
    $source = file_get_contents(public_path('manifest.webmanifest'));
    assert(is_string($source));
    /** @var array{name: string, start_url: string, scope: string, display: string, icons: list<array{src: string, sizes: string, type: string, purpose: string}>} $manifest */
    $manifest = json_decode($source, associative: true, flags: JSON_THROW_ON_ERROR);
    expect($manifest['name'])->toBe('Любимость')->and($manifest['start_url'])->toBe('/')
        ->and($manifest['scope'])->toBe('/')->and($manifest['display'])->toBe('standalone');
    foreach ($manifest['icons'] as $icon) {
        $size = getimagesize(public_path(ltrim($icon['src'], '/')));
        assert(is_array($size));
        expect(sprintf('%dx%d', $size[0], $size[1]))->toBe($icon['sizes'])->and($size['mime'])->toBe('image/png');
    }
    expect($manifest['icons'][1]['purpose'])->toContain('maskable');
});
