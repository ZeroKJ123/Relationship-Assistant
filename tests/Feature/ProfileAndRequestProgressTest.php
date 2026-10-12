<?php

declare(strict_types=1);

use App\Actions\Couples\EndCouple;
use App\Actions\Requests\CreatePartnerRequest;
use App\Actions\Requests\RespondToRequest;
use App\Data\Requests\NewRequestData;
use App\Data\Requests\RequestResponseData;
use App\Enums\DutyKind;
use App\Enums\DutyStatus;
use App\Enums\RequestResponse;
use App\Enums\RequestType;
use App\Models\Dish;
use App\Models\Duty;
use App\Models\RequestCondition;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AgreementScenario;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $this->withoutVite();
});

it('shows only the current partner public profile without private account settings', function (): void {
    $pair = AgreementScenario::create();
    $pair->second->forceFill(['bio' => 'Люблю готовить', 'payment_details' => 'private-account', 'compliments' => ['private-message']])->save();
    Dish::factory()->count(2)->create(['user_id' => $pair->second->id]);
    $this->asUser($pair->first)->get(route('profiles.show', $pair->second))->assertOk()->assertSee('Люблю готовить')->assertSee('Рецептов: 2')->assertDontSee('private-account')->assertDontSee('private-message')->assertDontSee('telegram_code');
    $this->get('/')->assertOk()->assertSee(route('profiles.show', $pair->second));
    $this->asUser(User::factory()->create())->get(route('profiles.show', $pair->second))->assertNotFound();
    app(EndCouple::class)->execute($pair->first, $pair->couple, $pair->couple->version);
    $this->asUser($pair->first)->get(route('profiles.show', $pair->second))->assertNotFound();
});

it('edits basic and additional settings independently and encrypts the description', function (): void {
    $user = User::factory()->create(['payment_details' => 'account', 'bio' => 'Старое описание']);
    $this->asUser($user)->patch(route('profile.update'), ['name' => 'Новое имя', 'gender' => 'unspecified', 'bio' => 'Новый профиль'])->assertSessionHasNoErrors();
    expect($user->refresh()->bio)->toBe('Новый профиль')->and($user->payment_details)->toBe('account')->and($user->getRawOriginal('bio'))->not->toContain('Новый профиль');
    $this->patch(route('profile.update'), ['settings_only' => '1', 'payment_details' => '', 'currency' => 'EUR'])->assertSessionHasNoErrors();
    expect($user->refresh()->name)->toBe('Новое имя')->and($user->bio)->toBe('Новый профиль')->and($user->payment_details)->toBeNull();
    $this->patch(route('profile.update'), ['settings_only' => '0'])->assertSessionHasErrors(['name', 'gender']);
});

it('executes and verifies a request directly from its page and updates the displayed status', function (): void {
    $pair = AgreementScenario::create();
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Service, 'Помыть посуду', CarbonImmutable::now()->addDay()));
    $url = route('requests.show', [$pair->couple, $request]);
    $this->asUser($pair->second)->get($url)->assertOk()->assertSee('Создано · ожидает ответа')->assertDontSee('Все просьбы');
    app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(RequestResponse::Agree, $request->version));
    $duty = $request->duties()->sole();
    $this->get($url)->assertOk()->assertSee('В процессе исполнения')->assertSee(route('duties.report', [$pair->couple, $duty]))->assertDontSee('Перейти к выполнению')->assertDontSee('Договорились.');
    $this->post(route('duties.report', [$pair->couple, $duty]), ['answer' => '1', 'confirmation' => '1', 'expected_version' => $duty->version, 'return_request' => '1'])->assertRedirect($url);
    $this->get($url)->assertSee('Ожидает подтверждения')->assertSee('status--warning', false);
    $this->asUser($pair->first)->post(route('duties.verify', [$pair->couple, $duty]), ['answer' => '1', 'confirmation' => '1', 'expected_version' => $duty->refresh()->version, 'return_request' => '1'])->assertRedirect($url);
    expect($duty->refresh()->status)->toBe(DutyStatus::Confirmed);
    $this->get($url)->assertSee('Выполнено')->assertSee('status--success', false);
});

it('keeps the condition unfinished when only the original duty was verified', function (): void {
    $pair = AgreementScenario::create();
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Service, 'Помочь', CarbonImmutable::now()->addDay()));
    app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(RequestResponse::Agree, $request->version));
    $request->duties()->sole()->forceFill(['status' => DutyStatus::Confirmed])->save();
    $condition = RequestCondition::factory()->create(['partner_request_id' => $request->id, 'accepted_at' => now()]);
    Duty::factory()->create(['kind' => DutyKind::Condition, 'request_condition_id' => $condition->id, 'partner_request_id' => $request->id, 'couple_id' => $pair->couple->id, 'executor_id' => $pair->first->id, 'reviewer_id' => $pair->second->id, 'status' => DutyStatus::Reported]);
    $this->asUser($pair->first)->get(route('requests.show', [$pair->couple, $request]))->assertOk()->assertSee('Ожидает подтверждения')->assertDontSee('Отменить просьбу');
});
