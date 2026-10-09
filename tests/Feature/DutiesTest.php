<?php

declare(strict_types=1);

use App\Actions\Admin\UpdateEconomySettings;
use App\Actions\Couples\EndCouple;
use App\Actions\Duties\CheckOverdueDuty;
use App\Actions\Duties\ReportDuty;
use App\Actions\Duties\VerifyDuty;
use App\Actions\Requests\CreatePartnerRequest;
use App\Actions\Requests\ResolveCondition;
use App\Actions\Requests\RespondToRequest;
use App\Data\Admin\EconomySettingsData;
use App\Data\Requests\NewRequestData;
use App\Data\Requests\RequestResponseData;
use App\Enums\ConditionType;
use App\Enums\DutyKind;
use App\Enums\DutyStatus;
use App\Enums\HeartOperationType;
use App\Enums\RequestResponse;
use App\Enums\RequestType;
use App\Models\Duty;
use App\Models\DutyConfirmation;
use App\Models\User;
use App\Services\Hearts\EconomySettingsStore;
use App\Services\Hearts\Ledger;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\AgreementScenario;
use Tests\Support\DutyScenario;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $this->withoutVite();
});

it('creates separate duties for the service and the accepted condition exactly once', function (): void {
    $pair = AgreementScenario::create();
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Guests, 'Гости', CarbonImmutable::now()->addDays(3)));
    expect(Duty::query()->count())->toBe(0);
    app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(response: RequestResponse::Conditional, expectedVersion: 0, conditionType: ConditionType::Action, description: 'Убрать стол', dueAt: CarbonImmutable::now()->addDays(4)));
    expect(Duty::query()->count())->toBe(0);
    app(ResolveCondition::class)->execute($pair->first, $pair->couple, $request, 1);
    app(ResolveCondition::class)->execute($pair->first, $pair->couple, $request, 1);
    expect(Duty::query()->count())->toBe(2);
    $service = $request->duties()->where('kind', DutyKind::Service)->sole();
    $condition = $request->duties()->where('kind', DutyKind::Condition)->sole();
    expect($service->executor_id)->toBe($pair->second->id);
    expect($condition->executor_id)->toBe($pair->first->id);
    app(ReportDuty::class)->execute($pair->second, $pair->couple, $service, version: 0, isDone: true);
    app(VerifyDuty::class)->execute($pair->first, $pair->couple, $service, version: 1, isConfirmed: true);
    expect($condition->refresh()->status)->toBe(DutyStatus::Pending);
    expect($condition->reported_at)->toBeNull();
});

it('requires explicit confirmation and lets only the executor report and only the partner verify', function (): void {
    $s = DutyScenario::create();
    $route = route('duties.report', [$s->pair->couple, $s->duty]);
    $this->asUser($s->pair->second)->get(route('duties.show', [$s->pair->couple, $s->duty]))->assertOk();
    $this->post($route, ['expected_version' => 0, 'answer' => 1])->assertSessionHasErrors('confirmation');
    expect(fn () => app(ReportDuty::class)->execute($s->pair->first, $s->pair->couple, $s->duty, version: 0, isDone: true))->toThrow(AuthorizationException::class);
    $this->post($route, ['expected_version' => 0, 'answer' => 1, 'confirmation' => 1])->assertRedirect();
    $this->post($route, ['expected_version' => 0, 'answer' => 1, 'confirmation' => 1])->assertRedirect();
    expect($s->duty->refresh()->status)->toBe(DutyStatus::Reported);
    expect($s->duty->reported_at)->not->toBeNull();
    expect($s->duty->reviewed_at)->toBeNull();
    expect($s->duty->confirmations()->count())->toBe(1);
    expect(app(Ledger::class)->getWallet($s->pair->second)->balance)->toBe(20);
    expect(fn () => app(VerifyDuty::class)->execute($s->pair->second, $s->pair->couple, $s->duty, version: 1, isConfirmed: true))->toThrow(AuthorizationException::class);
    $verify = route('duties.verify', [$s->pair->couple, $s->duty]);
    $this->asUser($s->pair->first)->post($verify, ['expected_version' => 1, 'answer' => 1])->assertSessionHasErrors('confirmation');
    $this->travel(1)->hours();
    $this->post($verify, ['expected_version' => 1, 'answer' => 1, 'confirmation' => 1])->assertRedirect();
    $this->post($verify, ['expected_version' => 1, 'answer' => 1, 'confirmation' => 1])->assertRedirect();
    expect($s->duty->refresh()->status)->toBe(DutyStatus::Confirmed);
    expect($s->duty->reviewed_at?->greaterThan($s->duty->reported_at))->toBeTrue();
    $wallet = app(Ledger::class)->getWallet($s->pair->second);
    expect($wallet->balance)->toBe(25);
    expect($wallet->transactions()->where('type', HeartOperationType::Reward)->count())->toBe(1);
    $this->travelBack();
});

it('records a confirmed not-done answer without pretending the work was reported', function (): void {
    $s = DutyScenario::create();
    app(ReportDuty::class)->execute($s->pair->second, $s->pair->couple, $s->duty, version: 0, isDone: false);
    app(ReportDuty::class)->execute($s->pair->second, $s->pair->couple, $s->duty, version: 0, isDone: false);
    expect($s->duty->refresh()->status)->toBe(DutyStatus::Pending);
    expect($s->duty->reported_at)->toBeNull();
    expect($s->duty->confirmations()->count())->toBe(1);
    expect(fn () => app(VerifyDuty::class)->execute($s->pair->first, $s->pair->couple, $s->duty, version: 1, isConfirmed: true))->toThrow(ValidationException::class);
});

it('does not penalize timely reported work awaiting late verification', function (bool $isConfirmed, int $balance): void {
    $s = DutyScenario::create();
    app(ReportDuty::class)->execute($s->pair->second, $s->pair->couple, $s->duty, version: 0, isDone: true);
    $this->travel(5)->days();
    app(CheckOverdueDuty::class)->execute($s->duty);
    $this->artisan('duties:check-overdue')->assertSuccessful();
    app(VerifyDuty::class)->execute($s->pair->first, $s->pair->couple, $s->duty, version: 1, isConfirmed: $isConfirmed);
    app(VerifyDuty::class)->execute($s->pair->first, $s->pair->couple, $s->duty, version: 1, isConfirmed: $isConfirmed);
    $wallet = app(Ledger::class)->getWallet($s->pair->second);
    expect($wallet->balance)->toBe($balance);
    expect($wallet->transactions()->where('type', HeartOperationType::OverduePenalty)->count())->toBe(0);
    expect($s->duty->refresh()->overdue_at)->toBeNull();
    $this->travelBack();
})->with([[true, 25], [false, 5]]);

it('charges overdue work once and still allows its later completion', function (): void {
    $s = DutyScenario::create();
    $this->travel(4)->days();
    $this->artisan('duties:check-overdue')->assertSuccessful();
    $this->artisan('duties:check-overdue')->assertSuccessful();
    expect($s->duty->refresh()->status)->toBe(DutyStatus::Overdue);
    app(ReportDuty::class)->execute($s->pair->second, $s->pair->couple, $s->duty, version: 1, isDone: true);
    app(VerifyDuty::class)->execute($s->pair->first, $s->pair->couple, $s->duty, version: 2, isConfirmed: true);
    $wallet = app(Ledger::class)->getWallet($s->pair->second);
    expect($wallet->balance)->toBe(15);
    expect($wallet->transactions()->where('type', HeartOperationType::OverduePenalty)->count())->toBe(1);
    $this->travelBack();
});

it('rejects stale buttons and keeps duty history private after the couple ends', function (): void {
    $s = DutyScenario::create();
    app(ReportDuty::class)->execute($s->pair->second, $s->pair->couple, $s->duty, version: 0, isDone: true);
    expect(fn () => app(VerifyDuty::class)->execute($s->pair->first, $s->pair->couple, $s->duty, version: 0, isConfirmed: true))->toThrow(ValidationException::class);
    $outsider = User::factory()->create(['is_admin' => true]);
    $this->asUser($outsider)->get(route('duties.show', [$s->pair->couple, $s->duty]))->assertForbidden();
    $other = AgreementScenario::create();
    $this->asUser($other->first)->get(route('duties.show', [$other->couple, $s->duty]))->assertNotFound();
    app(EndCouple::class)->execute($s->pair->first, $s->pair->couple);
    $this->asUser($s->pair->second)->get(route('duties.show', [$s->pair->couple, $s->duty]))->assertOk()->assertDontSee('Подтверждаю выбранный ответ о выполнении');
    expect(fn () => app(VerifyDuty::class)->execute($s->pair->first, $s->pair->couple, $s->duty, version: 1, isConfirmed: true))->toThrow(AuthorizationException::class);
});

it('freezes execution tariffs at acceptance and never rewards monetary payment', function (RequestType $type, int $balance): void {
    $s = DutyScenario::create($type);
    $settings = app(EconomySettingsStore::class)->initialize()->settings;
    $settings['reward'] = 10;
    app(UpdateEconomySettings::class)->execute(User::factory()->create(['is_admin' => true]), new EconomySettingsData(1, $settings));
    expect($s->duty->reward)->toBe(5);
    expect($s->duty->tariff_version)->toBe(1);
    app(ReportDuty::class)->execute($s->pair->second, $s->pair->couple, $s->duty, version: 0, isDone: true);
    app(VerifyDuty::class)->execute($s->pair->first, $s->pair->couple, $s->duty, version: 1, isConfirmed: true);
    expect(app(Ledger::class)->getWallet($s->pair->second)->balance)->toBe($balance);
})->with([[RequestType::Service, 25], [RequestType::Money, 20], [RequestType::Gift, 20]]);

it('provides a reported duty factory fixture that can be verified through the real action', function (): void {
    $confirmation = DutyConfirmation::factory()->create();
    $duty = Duty::query()->findOrFail($confirmation->duty_id);
    app(VerifyDuty::class)->execute($duty->reviewer, $duty->couple, $duty, version: 1, isConfirmed: true);
    expect(app(Ledger::class)->getWallet($duty->executor)->balance)->toBe(25);
    expect($duty->refresh()->status)->toBe(DutyStatus::Confirmed);
});
