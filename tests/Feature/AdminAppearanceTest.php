<?php

declare(strict_types=1);

use App\Models\AdminAudit;
use App\Models\AppearanceSetting;
use App\Models\Car;
use App\Models\User;
use App\Services\Appearance\AppearanceCatalog;
use App\Services\Appearance\AppearanceStore;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $this->withoutVite();
    config(['admin_panel.login' => 'panel-owner', 'admin_panel.password_hash' => Hash::make('test-panel-password')]);
});
afterEach(function (): void {
    $this->travelBack();
});

it('protects every appearance screen and write behind the second password', function (): void {
    $this->get(route('admin.panel.themes'))->assertRedirect(route('login'));
    $other = User::factory()->create(['is_admin' => true]);
    $this->asUser($other)->get(route('admin.panel.icons'))->assertNotFound();
    $this->put(route('admin.panel.appearance.update'), [])->assertNotFound();
    $owner = User::factory()->create(['login' => 'panel-owner']);
    $this->asUser($owner)->get(route('admin.panel.builder'))->assertRedirect(route('admin.panel.login'));
    $this->put(route('admin.panel.appearance.update'), [])->assertRedirect(route('admin.panel.login'));
    expect(AppearanceSetting::query()->sole()->revision)->toBe(0);
});

it('renders the theme library icons and visual builder with accessible controls', function (): void {
    $owner = User::factory()->create(['login' => 'panel-owner']);
    $this->asUser($owner)->post(route('admin.panel.unlock'), ['password' => 'test-panel-password'])->assertRedirect();
    $this->get(route('admin.panel.themes'))->assertOk()->assertSee('Весенний сад')->assertSee('Автоматически по сезонам');
    $this->get(route('admin.panel.themes', ['new' => 1]))->assertOk()->assertSee('Новая тема');
    $this->get(route('admin.panel.icons'))->assertOk()->assertSee('Своя картинка');
    $this->get(route('admin.panel.builder'))->assertOk()->assertSee('sandbox="allow-same-origin"', false)->assertSee('Колонок быстрых действий')->assertSee('Условие показа');
    if (getenv('HOMEASSIST_APPEARANCE_PREVIEW') === '1') {
        file_put_contents('/private/tmp/homeassist-builder-preview.html', $this->get(route('admin.panel.builder'))->getContent());
        file_put_contents('/private/tmp/homeassist-themes-preview.html', $this->get(route('admin.panel.themes'))->getContent());
    }
});

it('creates a theme with a validated public background and applies it to guest pages', function (): void {
    Storage::fake('appearance_assets');
    $owner = User::factory()->create(['login' => 'panel-owner']);
    $this->asUser($owner)->post(route('admin.panel.unlock'), ['password' => 'test-panel-password'])->assertRedirect();
    $theme = (new AppearanceCatalog)->defaults()['themes']['spring'];
    unset($theme['image']);
    $theme['name'] = 'Мой сад <script>alert(1)</script>';
    $theme['icons'] = ['kitchen' => '🌷'];
    $this->put(route('admin.panel.appearance.update'), ['section' => 'theme', 'revision' => 0, 'theme_id' => 'my-garden', 'theme' => $theme, 'image' => UploadedFile::fake()->image('garden.png', 300, 200), 'confirmation' => '1'])->assertSessionHasNoErrors()->assertRedirect(route('admin.panel.themes'));
    $record = AppearanceSetting::query()->sole();
    $asset = $record->settings['themes']['my-garden']['image'];
    Storage::disk('appearance_assets')->assertExists($asset);
    $this->put(route('admin.panel.appearance.update'), ['section' => 'selection', 'revision' => 1, 'active' => 'my-garden', 'seasonal' => '0', 'seasons' => array_combine(AppearanceCatalog::SEASONS, AppearanceCatalog::SEASONS), 'confirmation' => '1'])->assertSessionHasNoErrors();
    $this->get(route('admin.panel.themes'))->assertOk()->assertSee($theme['name'])->assertDontSee($theme['name'], false);
    $this->post(route('logout'))->assertRedirect();
    $this->get('/')->assertOk()->assertSee('--color-violet:#326841')->assertSee(route('appearance.asset', ['asset' => $asset]));
    $this->get(route('appearance.asset', ['asset' => $asset]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Type', 'image/png');
    $this->get('/appearance-assets/../../.env')->assertNotFound();
});

it('rejects executable theme values unknown keys and unsupported uploads', function (string $field, mixed $value, string $error): void {
    $owner = User::factory()->create(['login' => 'panel-owner']);
    $this->asUser($owner)->post(route('admin.panel.unlock'), ['password' => 'test-panel-password'])->assertRedirect();
    $theme = (new AppearanceCatalog)->defaults()['themes']['classic'];
    unset($theme['image']);
    $theme['icons'] = ['kitchen' => ''];
    $data = ['section' => 'theme', 'revision' => 0, 'theme_id' => 'custom', 'theme' => $theme, 'confirmation' => '1'];
    data_set($data, $field, $value);
    $this->put(route('admin.panel.appearance.update'), $data)->assertSessionHasErrors($error);
    expect(AppearanceSetting::query()->sole()->revision)->toBe(0);
})->with([
    'css injection' => ['theme.primary', '#000000;display:none', 'theme.primary'],
    'unexpected key' => ['theme.html', '<script>bad()</script>', 'theme'],
    'executable upload' => ['image', UploadedFile::fake()->create('icon.svg', 1, 'image/svg+xml'), 'image'],
    'missing confirmation' => ['confirmation', '0', 'confirmation'],
]);

it('switches seasons on the calendar boundary in Minsk', function (string $time, string $expected): void {
    $this->travelTo(CarbonImmutable::parse($time, 'Europe/Minsk'));
    $record = AppearanceSetting::query()->sole();
    $settings = $record->settings;
    $settings['seasonal'] = true;
    $record->forceFill(['settings' => $settings])->save();
    expect(app(AppearanceStore::class)->activeId())->toBe($expected);
})->with([
    ['2026-02-28 23:59:59', 'winter'], ['2026-03-01 00:00:00', 'spring'],
    ['2026-06-01 00:00:00', 'summer'], ['2026-09-01 00:00:00', 'autumn'], ['2026-12-01 00:00:00', 'winter'],
]);

it('rejects stale saves and non-existent seasonal themes without changing other settings', function (): void {
    $owner = User::factory()->create(['login' => 'panel-owner']);
    $this->asUser($owner)->post(route('admin.panel.unlock'), ['password' => 'test-panel-password'])->assertRedirect();
    $data = ['section' => 'icons', 'revision' => 0, 'icon' => 'home', 'kind' => 'glyph', 'glyph' => '🏡', 'confirmation' => '1'];
    $this->put(route('admin.panel.appearance.update'), $data)->assertSessionHasNoErrors();
    $data['glyph'] = '❌';
    $this->put(route('admin.panel.appearance.update'), $data)->assertSessionHasErrors('revision');
    $this->put(route('admin.panel.appearance.update'), ['section' => 'selection', 'revision' => 1, 'active' => 'missing', 'seasonal' => 1, 'seasons' => array_combine(AppearanceCatalog::SEASONS, AppearanceCatalog::SEASONS), 'confirmation' => '1'])->assertSessionHasErrors('active');
    expect(AppearanceSetting::query()->sole()->settings['icons']['home']['value'])->toBe('🏡');
    expect(AppearanceSetting::query()->sole()->revision)->toBe(1);
    expect(AdminAudit::query()->where('operation', 'appearance.icons')->count())->toBe(1);
});

it('uses global icons before theme icons and restores the theme when the override is removed', function (): void {
    $owner = User::factory()->create(['login' => 'panel-owner']);
    $record = AppearanceSetting::query()->sole();
    $settings = $record->settings;
    $settings['themes']['classic']['icons']['home'] = '🌸';
    $record->forceFill(['settings' => $settings])->save();
    $this->asUser($owner)->post(route('admin.panel.unlock'), ['password' => 'test-panel-password'])->assertRedirect();
    $this->get('/')->assertOk()->assertSee('🌸');
    $this->put(route('admin.panel.appearance.update'), ['section' => 'icons', 'revision' => 0, 'icon' => 'home', 'kind' => 'glyph', 'glyph' => '🏡', 'confirmation' => 1])->assertSessionHasNoErrors();
    $this->get('/')->assertOk()->assertSee('🏡')->assertDontSee('🌸');
    $this->put(route('admin.panel.appearance.update'), ['section' => 'icons', 'revision' => 1, 'icon' => 'home', 'kind' => 'default', 'confirmation' => 1])->assertSessionHasNoErrors();
    $this->get('/')->assertOk()->assertSee('🌸')->assertDontSee('🏡');
});

it('uploads a reusable icon and refuses an image replacement without a file', function (): void {
    Storage::fake('appearance_assets');
    $owner = User::factory()->create(['login' => 'panel-owner']);
    $this->asUser($owner)->post(route('admin.panel.unlock'), ['password' => 'test-panel-password'])->assertRedirect();
    $data = ['section' => 'icons', 'revision' => 0, 'icon' => 'home', 'kind' => 'image', 'confirmation' => 1];
    $this->put(route('admin.panel.appearance.update'), $data)->assertSessionHasErrors('image');
    $data['image'] = UploadedFile::fake()->image('home.png');
    $this->put(route('admin.panel.appearance.update'), $data)->assertSessionHasNoErrors();
    $asset = AppearanceSetting::query()->sole()->settings['icons']['home']['value'];
    Storage::disk('appearance_assets')->assertExists($asset);
    $this->get('/')->assertOk()->assertSee(route('appearance.asset', ['asset' => $asset]));
});

it('persists block order visibility labels and text while keeping the car condition dynamic', function (): void {
    $owner = User::factory()->create(['login' => 'panel-owner']);
    $this->asUser($owner)->post(route('admin.panel.unlock'), ['password' => 'test-panel-password'])->assertRedirect();
    $layout = (new AppearanceCatalog)->defaults()['layout'];
    $layout['columns'] = '3';
    $layout['blocks'] = array_reverse($layout['blocks']);
    $layout['blocks'][] = ['id' => 'text-1', 'type' => 'text', 'enabled' => '1', 'condition' => 'always', 'tone' => 'pink', 'title' => 'Личный заголовок', 'text' => '<script>alert(1)</script>'];
    $layout['tiles'][0]['enabled'] = '0';
    $layout['tiles'][4]['title'] = 'Наш кинозал';
    $this->put(route('admin.panel.appearance.update'), ['section' => 'layout', 'revision' => '0', 'layout' => $layout, 'confirmation' => '1'])->assertSessionHasNoErrors();
    $this->get('/')->assertOk()->assertSeeInOrder(['data-layout-block="compliment"', 'data-layout-block="greeting"', 'data-layout-block="text-1"'], false)->assertDontSee('data-layout-tile="kitchen"', false)->assertDontSee('data-layout-tile="car"', false)->assertSee('Наш кинозал')->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false)->assertSee('--tile-columns:3');
    Car::factory()->create(['user_id' => $owner->id]);
    $this->get('/')->assertOk()->assertSee('data-layout-tile="car"', false);
    expect(AppearanceSetting::query()->sole()->settings['layout']['tiles'][0]['enabled'])->toBeFalse();
});

it('rejects unsupported visibility conditions duplicate blocks and unknown destinations', function (string $field, mixed $value, string $error): void {
    $owner = User::factory()->create(['login' => 'panel-owner']);
    $this->asUser($owner)->post(route('admin.panel.unlock'), ['password' => 'test-panel-password'])->assertRedirect();
    $layout = (new AppearanceCatalog)->defaults()['layout'];
    data_set($layout, $field, $value);
    $this->put(route('admin.panel.appearance.update'), ['section' => 'layout', 'revision' => 0, 'layout' => $layout, 'confirmation' => 1])->assertSessionHasErrors($error);
    expect(AppearanceSetting::query()->sole()->revision)->toBe(0);
})->with([
    ['tiles.0.condition', 'eval(user.code)', 'layout.tiles.0.condition'],
    ['tiles.0.id', 'admin', 'layout.tiles.0.id'],
    ['blocks.1.type', 'greeting', 'layout.blocks'],
    ['blocks.1.id', 'greeting', 'layout.blocks.1.id'],
    ['columns', 5, 'layout.columns'],
]);

it('evaluates saved visibility rules against the current user without granting extra access', function (string $condition, bool $isVisible): void {
    $owner = User::factory()->create(['login' => 'panel-owner']);
    $this->asUser($owner)->post(route('admin.panel.unlock'), ['password' => 'test-panel-password'])->assertRedirect();
    $layout = (new AppearanceCatalog)->defaults()['layout'];
    $layout['blocks'][] = ['id' => 'text-rule', 'type' => 'text', 'enabled' => true, 'condition' => $condition, 'tone' => 'plain', 'title' => 'Проверка видимости', 'text' => 'Текст'];
    $this->put(route('admin.panel.appearance.update'), ['section' => 'layout', 'revision' => 0, 'layout' => $layout, 'confirmation' => 1])->assertSessionHasNoErrors();
    $response = $this->get('/')->assertOk();
    if ($isVisible) {
        $response->assertSee('data-layout-block="text-rule"', false);

        return;
    }
    $response->assertDontSee('data-layout-block="text-rule"', false);
})->with([
    ['always', true], ['has_partner', false], ['no_partner', true],
    ['has_car', false], ['no_car', true], ['positive_balance', true],
    ['has_duties', false], ['has_request', false],
]);
