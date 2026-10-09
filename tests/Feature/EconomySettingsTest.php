<?php

declare(strict_types=1);
use App\Actions\Admin\UpdateEconomySettings;
use App\Actions\Duties\ReportDuty;
use App\Actions\Duties\VerifyDuty;
use App\Data\Admin\EconomySettingsData;
use App\Enums\HeartOperationType;
use App\Models\EconomySettingsVersion;
use App\Models\User;
use App\Services\Hearts\EconomySettingsStore;
use App\Services\Hearts\Ledger;
use Database\Seeders\EconomySettingsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\DutyScenario;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $this->withoutVite();
});
it('initializes settings once and permits only administrators to create audited immutable versions', function (): void {
    $this->seed(EconomySettingsSeeder::class);
    $this->seed(EconomySettingsSeeder::class);
    expect(EconomySettingsVersion::query()->count())->toBe(1);
    $initial = app(EconomySettingsStore::class)->initialize();
    $settings = $initial->settings;
    $settings['reward'] = 6;
    $user = User::factory()->create();
    expect(fn () => app(UpdateEconomySettings::class)->execute($user, new EconomySettingsData(1, $settings)))->toThrow(AuthorizationException::class);
    $this->asUser($user)->get(route('admin.economy.index'))->assertForbidden();
    $admin = User::factory()->create(['is_admin' => true]);
    $updated = app(UpdateEconomySettings::class)->execute($admin, new EconomySettingsData(1, $settings));
    expect($updated->version)->toBe(2);
    expect($updated->created_by_id)->toBe($admin->id);
    expect(app(UpdateEconomySettings::class)->execute($admin, new EconomySettingsData(1, $settings))->id)->toBe($updated->id);
    expect(fn () => $initial->forceFill(['settings' => $settings])->save())->toThrow(LogicException::class);
    expect(fn () => $initial->delete())->toThrow(LogicException::class);
    $this->asUser($admin)->get(route('admin.economy.index'))->assertOk()->assertSee('Сохранить правила');
    $this->put(route('admin.economy.update'), ['expected_version' => 2, ...$settings])->assertSessionHasErrors('confirmation');
    $this->put(route('admin.economy.update'), ['expected_version' => 2, 'confirmation' => 1, ...$settings])->assertRedirect();
    expect(EconomySettingsVersion::query()->count())->toBe(2);
});
it('rejects invalid combinations and stale edits without changing rules', function (array $changes): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $initial = app(EconomySettingsStore::class)->initialize();
    expect(fn () => app(UpdateEconomySettings::class)->execute($admin, new EconomySettingsData(1, array_replace($initial->settings, $changes))))->toThrow(ValidationException::class);
    expect(EconomySettingsVersion::query()->count())->toBe(1);
})->with([[['reward' => -1]], [['positive_cap' => 0]], [['starting_bonus' => 101]], [['reward' => 16]], [['reward_daily' => 76]], [['manual_thanks_daily' => 51]], [['manual_deduction_weekly' => 9]], [['unknown' => 1]]]);
it('keeps accepted tariffs and existing balances while new operations use the new version', function (): void {
    $this->seed(EconomySettingsSeeder::class);
    $s = DutyScenario::create();
    app(Ledger::class)->apply($s->pair->first, 60, HeartOperationType::Reward, 'test:existing-balance');
    $initial = app(EconomySettingsStore::class)->initialize();
    $settings = $initial->settings;
    $settings['reward'] = 8;
    $settings['starting_bonus'] = 10;
    $settings['positive_cap'] = 50;
    $admin = User::factory()->create(['is_admin' => true]);
    app(UpdateEconomySettings::class)->execute($admin, new EconomySettingsData(1, $settings));
    expect(fn () => app(UpdateEconomySettings::class)->execute($admin, new EconomySettingsData(1, $initial->settings)))->toThrow(ValidationException::class);
    expect(app(Ledger::class)->getWallet($s->pair->first)->balance)->toBe(80);
    app(ReportDuty::class)->execute($s->pair->second, $s->pair->couple, $s->duty, version: 0, isDone: true);
    app(VerifyDuty::class)->execute($s->pair->first, $s->pair->couple, $s->duty, version: 1, isConfirmed: true);
    expect($s->duty->reward)->toBe(5);
    expect(app(Ledger::class)->getWallet($s->pair->second)->balance)->toBe(15);
    $next = DutyScenario::forPair($s->pair);
    expect($next->duty->reward)->toBe(8);
    expect($next->duty->tariff_version)->toBe(2);
    expect(app(Ledger::class)->getWallet(User::factory()->create())->balance)->toBe(10);
});

it('persists bootstrap rules on first use and ignores subsequent configuration changes', function (): void {
    $initial = app(EconomySettingsStore::class)->read();
    config(['economy.reward' => 10]);
    expect(app(EconomySettingsStore::class)->read())->toBe($initial);
    expect(EconomySettingsVersion::query()->count())->toBe(1);
});
