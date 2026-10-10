<?php

declare(strict_types=1);

use App\Actions\Money\CreateMoneyRequest;
use App\Data\Money\MoneyRequestData;
use App\Enums\Currency;
use App\Models\User;
use App\Services\Hearts\EconomySettingsStore;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Tests\Support\AgreementScenario;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('saves a personal currency without changing partner or existing monetary agreements', function (): void {
    $pair = AgreementScenario::create();
    $pair->first->forceFill(['payment_details' => 'TEST original account'])->save();
    $request = app(CreateMoneyRequest::class)->execute($pair->first, $pair->couple, new MoneyRequestData('12.30', Currency::Usd, null, CarbonImmutable::now()->addDays(3), Str::uuid()->toString()));
    $this->asUser($pair->first)->patch(route('profile.update'), ['name' => $pair->first->name, 'gender' => 'unspecified', 'currency' => 'EUR', 'payment_details' => 'TEST changed account'])->assertRedirect(route('profile.show'));

    expect($pair->first->fresh()->currency)->toBe(Currency::Eur)
        ->and($pair->second->fresh()->currency)->toBe(Currency::Byn)
        ->and($pair->couple->fresh()->currency)->toBe(Currency::Byn)
        ->and($request->moneyRequest()->sole()->currency)->toBe(Currency::Usd)
        ->and($request->moneyRequest()->sole()->amount)->toBe('12.30');
    $this->get(route('money.create', $pair->couple))->assertOk()->assertSee('value="BYN" selected', escape: false);
    $this->actingAs($pair->first->fresh())->get(route('profile.show'))->assertOk()->assertSee('value="EUR" selected', escape: false)->assertSee('валюта пары: BYN');
});

it('retains a saved currency when an older client omits the preference', function (): void {
    $user = User::factory()->create(['currency' => Currency::Eur]);
    $this->asUser($user)->patch(route('profile.update'), ['name' => 'Новое имя', 'gender' => 'unspecified'])->assertRedirect(route('profile.show'));
    expect($user->fresh()->currency)->toBe(Currency::Eur);
});

it('rejects invalid currency preferences before changing the profile', function (string|array $currency): void {
    $user = User::factory()->create(['currency' => Currency::Byn]);
    $this->asUser($user)->from(route('profile.show'))->patch(route('profile.update'), ['name' => 'Не сохранять', 'gender' => 'unspecified', 'currency' => $currency, 'payment_details' => 'TEST rejected account'])->assertSessionHasErrors('currency')->assertSessionMissing('_old_input.payment_details');
    expect($user->fresh()->currency)->toBe(Currency::Byn)->and($user->fresh()->name)->toBe($user->name);
})->with(['unsupported' => ['GBP'], 'array' => [['EUR']]]);

it('offers personal sections without inventing a current couple or exposing administration', function (): void {
    config(['telegram.enabled' => false]);
    $user = User::factory()->create();
    $pair = AgreementScenario::create();
    $this->asUser($user)->get(route('profile.show'))->assertOk()
        ->assertSee(route('hearts.index'))->assertSee(route('friends.index'))
        ->assertSee('Подключение временно недоступно')
        ->assertDontSee(route('mood.index', $pair->couple))
        ->assertDontSee(route('admin.economy.index'));
});

it('links only the authenticated current couple from the profile', function (): void {
    $pair = AgreementScenario::create();
    $otherPair = AgreementScenario::create();
    $this->asUser($pair->first)->get(route('profile.show'))->assertOk()
        ->assertSee(route('mood.index', $pair->couple))->assertSee(route('reminders.index', $pair->couple))
        ->assertDontSee(route('mood.index', $otherPair->couple));
});

it('renders every economy setting once in grouped controls while denying regular accounts', function (): void {
    $user = User::factory()->create();
    $this->asUser($user)->get(route('admin.economy.index'))->assertForbidden();
    $admin = User::factory()->create(['is_admin' => true]);
    $response = $this->asUser($admin)->get(route('admin.economy.index'))->assertOk();
    $response->assertSee('Благодарности партнёру')->assertSee('История правил');
    foreach (array_keys(app(EconomySettingsStore::class)->labels()) as $key) {
        expect(substr_count($response->getContent(), sprintf('name="%s"', $key)))->toBe(1);
    }
});

it('associates currency and private details errors with their fields and restores ordinary input', function (): void {
    $user = User::factory()->create(['payment_details' => 'TEST saved account']);
    $errors = (new ViewErrorBag)->put('default', new MessageBag(['currency' => 'Выберите поддерживаемую валюту.', 'payment_details' => 'Сократите реквизиты.']));
    $this->asUser($user)->withSession(['errors' => $errors, '_old_input' => ['currency' => 'EUR']]);
    session()->save();
    $this->get(route('profile.show'))->assertOk()->assertSee('id="currency-error"', escape: false)
        ->assertSee('id="payment_details-error"', escape: false)->assertSee('aria-describedby="currency-error"', escape: false)
        ->assertSee('value="EUR" selected', escape: false)->assertSee('TEST saved account');
});
