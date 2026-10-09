<?php

declare(strict_types=1);

use App\Actions\Couples\Agreements\AcceptProposal;
use App\Actions\Couples\EndCouple;
use App\Actions\Requests\CreatePartnerRequest;
use App\Actions\Requests\ResolveCondition;
use App\Actions\Requests\RespondToRequest;
use App\Data\Requests\NewRequestData;
use App\Data\Requests\RequestResponseData;
use App\Enums\ConditionType;
use App\Enums\DeclineReason;
use App\Enums\RequestResponse;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\PartnerRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\AgreementScenario;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

it('creates a private typed request using the couple timezone and ignores injected participants', function (): void {
    $scenario = AgreementScenario::create();
    $this->asUser($scenario->first)->get(route('requests.create', $scenario->couple))->assertOk();
    $this->post(route('requests.store', $scenario->couple), ['operation_key' => Str::uuid()->toString(), 'type' => 'service', 'description' => '<script>secret</script>', 'due_at' => CarbonImmutable::tomorrow()->format('Y-m-d').'T18:00', 'recipient_id' => $scenario->first->id, 'status' => 'accepted'])->assertRedirect();
    $request = PartnerRequest::query()->sole();
    expect($request->recipient_id)->toBe($scenario->second->id);
    expect($request->status)->toBe(RequestStatus::Pending);
    expect($request->due_at->format('H:i'))->toBe('15:00');
    expect($request->refusal_tariff)->toBe(0);
    expect($request->events()->count())->toBe(1);
    $this->asUser($scenario->second)->get(route('requests.show', [$scenario->couple, $request]))->assertOk()->assertSee('&lt;script&gt;secret&lt;/script&gt;', false)->assertDontSee('<script>secret</script>', false);
    $outsider = User::factory()->create(['is_admin' => true]);
    $this->asUser($outsider)->get(route('requests.show', [$scenario->couple, $request]))->assertForbidden();
});

it('requires a deadline and rejects malformed dates and premature economy flags', function (array $changes, string $field): void {
    $scenario = AgreementScenario::create();
    $payload = array_replace(['operation_key' => Str::uuid()->toString(), 'type' => 'food', 'description' => 'Суп', 'due_at' => CarbonImmutable::now()->addDays(2)->format('Y-m-d\TH:i')], $changes);
    $this->asUser($scenario->first)->post(route('requests.store', $scenario->couple), $payload)->assertSessionHasErrors($field);
    expect(PartnerRequest::query()->count())->toBe(0);
})->with([[['due_at' => null], 'due_at'], [['due_at' => '2020-01-01T12:00'], 'due_at'], [['due_at' => '2027-02-31T12:00'], 'due_at'], [['type' => 'unknown'], 'type'], [['is_very_wanted' => true], 'partner_request'], [['description' => ['bad']], 'description']]);

it('accepts only the recipient response and makes its repeated acceptance idempotent', function (): void {
    $scenario = AgreementScenario::create();
    $request = app(CreatePartnerRequest::class)->execute($scenario->first, $scenario->couple, new NewRequestData(RequestType::Service, 'Помощь', CarbonImmutable::tomorrow()));
    expect(fn () => app(RespondToRequest::class)->execute($scenario->first, $scenario->couple, $request, new RequestResponseData(RequestResponse::Agree, 0)))->toThrow(AuthorizationException::class);
    $route = route('requests.responses.store', [$scenario->couple, $request]);
    $this->asUser($scenario->second)->post($route, ['response' => 'agree', 'expected_version' => 0])->assertSessionHasErrors('confirmation');
    $this->post($route, ['response' => 'agree', 'expected_version' => 0, 'confirmation' => 1])->assertRedirect();
    $this->post($route, ['response' => 'agree', 'expected_version' => 0, 'confirmation' => 1])->assertRedirect();
    expect($request->refresh()->status)->toBe(RequestStatus::Accepted);
    expect($request->accepted_at)->not->toBeNull();
    expect($request->events()->count())->toBe(2);
    expect(fn () => app(RespondToRequest::class)->execute($scenario->second, $scenario->couple, $request, new RequestResponseData(RequestResponse::Decline, 1, DeclineReason::Ordinary)))->toThrow(ValidationException::class);
});

it('requires an independently accepted current condition and preserves previous versions', function (): void {
    $scenario = AgreementScenario::create();
    $request = app(CreatePartnerRequest::class)->execute($scenario->first, $scenario->couple, new NewRequestData(RequestType::Guests, 'Гости', CarbonImmutable::now()->addDays(3)));
    $respond = app(RespondToRequest::class);
    $respond->execute($scenario->second, $scenario->couple, $request, new RequestResponseData(response: RequestResponse::Conditional, expectedVersion: 0, reason: null, conditionType: ConditionType::Action, description: 'Убрать стол', dueAt: CarbonImmutable::tomorrow()));
    expect($request->refresh()->status)->toBe(RequestStatus::Conditional);
    expect($request->accepted_at)->toBeNull();
    expect(fn () => app(ResolveCondition::class)->execute($scenario->second, $scenario->couple, $request, 1))->toThrow(AuthorizationException::class);
    $respond->execute($scenario->second, $scenario->couple, $request, new RequestResponseData(response: RequestResponse::Conditional, expectedVersion: 1, reason: null, conditionType: ConditionType::Kisses, description: 'Пять поцелуев', dueAt: CarbonImmutable::tomorrow()));
    expect(fn () => app(ResolveCondition::class)->execute($scenario->first, $scenario->couple, $request, 1))->toThrow(ValidationException::class);
    $route = route('requests.condition.store', [$scenario->couple, $request]);
    $this->asUser($scenario->first)->post($route, ['expected_version' => 2, 'decision' => 'accept'])->assertSessionHasErrors('confirmation');
    $this->post($route, ['expected_version' => 2, 'decision' => 'accept', 'confirmation' => 1])->assertRedirect();
    app(ResolveCondition::class)->execute($scenario->first, $scenario->couple, $request, 2);
    expect($request->refresh()->status)->toBe(RequestStatus::Accepted);
    expect($request->conditions()->where('version', 1)->sole()->accepted_at)->toBeNull();
    expect($request->conditions()->where('version', 2)->sole()->accepted_at)->not->toBeNull();
    expect($request->events()->count())->toBe(4);
    expect(fn () => $respond->execute($scenario->second, $scenario->couple, $request, new RequestResponseData(response: RequestResponse::Conditional, expectedVersion: 3, reason: null, conditionType: ConditionType::Custom, description: 'Другое', dueAt: CarbonImmutable::tomorrow())))->toThrow(ValidationException::class);
});

it('allows the author to decline a condition without charging the recipient', function (): void {
    $scenario = AgreementScenario::create();
    $request = app(CreatePartnerRequest::class)->execute($scenario->first, $scenario->couple, new NewRequestData(RequestType::Service, 'Просьба', CarbonImmutable::tomorrow()));
    app(RespondToRequest::class)->execute($scenario->second, $scenario->couple, $request, new RequestResponseData(response: RequestResponse::Conditional, expectedVersion: 0, reason: null, conditionType: ConditionType::Custom, description: 'Условие', dueAt: CarbonImmutable::tomorrow()));
    app(ResolveCondition::class)->execute($scenario->first, $scenario->couple, $request, 1, isAccepted: false);
    app(ResolveCondition::class)->execute($scenario->first, $scenario->couple, $request, 1, isAccepted: false);
    expect($request->refresh()->status)->toBe(RequestStatus::Declined);
    expect($request->decline_penalty)->toBe(0);
    expect($request->events()->count())->toBe(3);
    expect(fn () => app(ResolveCondition::class)->execute($scenario->first, $scenario->couple, $request, 1, isAccepted: true))->toThrow(ValidationException::class);
});

it('requires a future condition deadline even through a direct business action', function (): void {
    $scenario = AgreementScenario::create();
    $request = app(CreatePartnerRequest::class)->execute($scenario->first, $scenario->couple, new NewRequestData(RequestType::Food, 'Суп', CarbonImmutable::tomorrow()));
    expect(fn () => app(RespondToRequest::class)->execute($scenario->second, $scenario->couple, $request, new RequestResponseData(response: RequestResponse::Conditional, expectedVersion: 0, reason: null, conditionType: ConditionType::Action, description: 'Убрать', dueAt: null)))->toThrow(ValidationException::class);
    expect($request->conditions()->count())->toBe(0);
    expect($request->refresh()->version)->toBe(0);
});

it('freezes the refusal tariff and exempts lack of food money even for very wanted requests', function (DeclineReason $reason, int $penalty): void {
    $scenario = AgreementScenario::create();
    expect(fn () => app(CreatePartnerRequest::class)->execute($scenario->first, $scenario->couple, new NewRequestData(type: RequestType::Food, description: 'Суп', dueAt: CarbonImmutable::tomorrow(), isVeryWanted: true)))->toThrow(ValidationException::class);
    $proposal = $scenario->answerBoth(3, 3);
    app(AcceptProposal::class)->execute($scenario->first, $scenario->couple, $proposal);
    app(AcceptProposal::class)->execute($scenario->second, $scenario->couple, $proposal);
    $request = app(CreatePartnerRequest::class)->execute($scenario->first, $scenario->couple, new NewRequestData(type: RequestType::Food, description: 'Суп', dueAt: CarbonImmutable::tomorrow(), isVeryWanted: true));
    expect($request->refusal_tariff)->toBe(15);
    expect($request->refusal_tariff_version)->toBe(1);
    config(['economy.version' => 2, 'economy.refusal_tariff' => 20]);
    app(RespondToRequest::class)->execute($scenario->second, $scenario->couple, $request, new RequestResponseData(RequestResponse::Decline, 0, $reason));
    app(RespondToRequest::class)->execute($scenario->second, $scenario->couple, $request, new RequestResponseData(RequestResponse::Decline, 0, $reason));
    expect($request->refresh()->decline_penalty)->toBe($penalty);
    expect($request->events()->count())->toBe(2);
})->with([[DeclineReason::Ordinary, 15], [DeclineReason::NoFoodMoney, 0]]);

it('rejects the food exemption on unrelated requests', function (): void {
    $scenario = AgreementScenario::create();
    $request = app(CreatePartnerRequest::class)->execute($scenario->first, $scenario->couple, new NewRequestData(RequestType::Service, 'Помощь', CarbonImmutable::tomorrow()));
    expect(fn () => app(RespondToRequest::class)->execute($scenario->second, $scenario->couple, $request, new RequestResponseData(RequestResponse::Decline, 0, DeclineReason::NoFoodMoney)))->toThrow(ValidationException::class);
    expect($request->refresh()->status)->toBe(RequestStatus::Pending);
});

it('keeps history but blocks all transitions after the couple ends', function (): void {
    $scenario = AgreementScenario::create();
    $request = app(CreatePartnerRequest::class)->execute($scenario->first, $scenario->couple, new NewRequestData(RequestType::Service, 'Помощь', CarbonImmutable::tomorrow()));
    $other = AgreementScenario::create();
    $this->asUser($other->first)->get(route('requests.show', [$other->couple, $request]))->assertNotFound();
    app(EndCouple::class)->execute($scenario->first, $scenario->couple);
    $this->asUser($scenario->second)->get(route('requests.show', [$scenario->couple, $request]))->assertOk()->assertDontSee('Подтверждаю выбранный ответ');
    expect(fn () => app(RespondToRequest::class)->execute($scenario->second, $scenario->couple, $request, new RequestResponseData(RequestResponse::Agree, 0)))->toThrow(AuthorizationException::class);
    expect($request->refresh()->status)->toBe(RequestStatus::Pending);
});

it('refuses acceptance once the request or the condition deadline has expired', function (): void {
    $scenario = AgreementScenario::create();
    $request = app(CreatePartnerRequest::class)->execute($scenario->first, $scenario->couple, new NewRequestData(RequestType::Service, 'Помощь', CarbonImmutable::now()->addDays(3)));
    app(RespondToRequest::class)->execute($scenario->second, $scenario->couple, $request, new RequestResponseData(response: RequestResponse::Conditional, expectedVersion: 0, reason: null, conditionType: ConditionType::Action, description: 'Убрать', dueAt: CarbonImmutable::tomorrow()));
    $this->travel(2)->days();
    expect(fn () => app(ResolveCondition::class)->execute($scenario->first, $scenario->couple, $request, 1))->toThrow(ValidationException::class);
    expect($request->refresh()->status)->toBe(RequestStatus::Conditional);
    $this->travelBack();
});

it('deduplicates retried creation and rejects reuse of its operation key with changed content', function (): void {
    $scenario = AgreementScenario::create();
    $key = Str::uuid()->toString();
    $data = new NewRequestData(type: RequestType::Service, description: 'Помощь', dueAt: CarbonImmutable::tomorrow(), operationKey: $key);
    $first = app(CreatePartnerRequest::class)->execute($scenario->first, $scenario->couple, $data);
    $second = app(CreatePartnerRequest::class)->execute($scenario->first, $scenario->couple, $data);
    expect($first->id)->toBe($second->id);
    expect($first->events()->count())->toBe(1);
    expect(fn () => app(CreatePartnerRequest::class)->execute($scenario->first, $scenario->couple, new NewRequestData(type: RequestType::Service, description: 'Другая просьба', dueAt: CarbonImmutable::tomorrow(), operationKey: $key)))->toThrow(ValidationException::class);
    expect(PartnerRequest::query()->count())->toBe(1);
});
