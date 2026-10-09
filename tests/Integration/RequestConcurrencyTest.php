<?php

declare(strict_types=1);

use App\Actions\Requests\CreatePartnerRequest;
use App\Actions\Requests\RespondToRequest;
use App\Data\Requests\NewRequestData;
use App\Data\Requests\RequestResponseData;
use App\Enums\ConditionType;
use App\Enums\RequestResponse;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\PartnerRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Support\AgreementScenario;
use Tests\Support\ConcurrentOperations;

uses(DatabaseMigrations::class);

it('keeps concurrent repeated agreement idempotent', function (): void {
    $scenario = AgreementScenario::create();
    $request = app(CreatePartnerRequest::class)->execute($scenario->first, $scenario->couple, new NewRequestData(RequestType::Service, 'Test', CarbonImmutable::now()->addDays(3)));
    $attempt = [(string) $scenario->second->id, (string) $scenario->couple->id, 'request_agree', (string) $request->id, '0'];
    expect((new ConcurrentOperations)->run('agreement-worker.php', [$attempt, $attempt]))->toBe(['accepted', 'accepted']);
    expect($request->refresh()->status)->toBe(RequestStatus::Accepted);
    expect($request->events()->count())->toBe(2);
});

it('serializes competing agreement and refusal against one current version', function (): void {
    $scenario = AgreementScenario::create();
    $request = app(CreatePartnerRequest::class)->execute($scenario->first, $scenario->couple, new NewRequestData(RequestType::Service, 'Test', CarbonImmutable::now()->addDays(3)));
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [
        [(string) $scenario->second->id, (string) $scenario->couple->id, 'request_agree', (string) $request->id, '0'],
        [(string) $scenario->second->id, (string) $scenario->couple->id, 'request_decline', (string) $request->id, '0'],
    ]);
    sort($results);
    expect($results)->toBe(['accepted', 'rejected']);
    expect($request->refresh()->version)->toBe(1);
    expect($request->events()->count())->toBe(2);
});

it('never accepts a condition replaced concurrently', function (): void {
    $scenario = AgreementScenario::create();
    $request = app(CreatePartnerRequest::class)->execute($scenario->first, $scenario->couple, new NewRequestData(RequestType::Service, 'Test', CarbonImmutable::now()->addDays(3)));
    app(RespondToRequest::class)->execute($scenario->second, $scenario->couple, $request, new RequestResponseData(response: RequestResponse::Conditional, expectedVersion: 0, reason: null, conditionType: ConditionType::Action, description: 'Test', dueAt: CarbonImmutable::tomorrow()));
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [
        [(string) $scenario->first->id, (string) $scenario->couple->id, 'request_accept_condition', (string) $request->id, '1'],
        [(string) $scenario->second->id, (string) $scenario->couple->id, 'request_condition', (string) $request->id, '1'],
    ]);
    sort($results);
    expect($results)->toBe(['accepted', 'rejected']);
    $request->refresh();
    if ($request->status === RequestStatus::Accepted) {
        expect($request->conditions()->count())->toBe(1);
        expect($request->conditions()->sole()->accepted_at)->not->toBeNull();

        return;
    }
    expect($request->status)->toBe(RequestStatus::Conditional);
    expect($request->conditions()->whereNotNull('accepted_at')->count())->toBe(0);
    expect($request->conditions()->count())->toBe(2);
});

it('creates just one accepted condition event when the author confirms concurrently', function (): void {
    $scenario = AgreementScenario::create();
    $request = app(CreatePartnerRequest::class)->execute($scenario->first, $scenario->couple, new NewRequestData(RequestType::Service, 'Test', CarbonImmutable::now()->addDays(3)));
    app(RespondToRequest::class)->execute($scenario->second, $scenario->couple, $request, new RequestResponseData(response: RequestResponse::Conditional, expectedVersion: 0, reason: null, conditionType: ConditionType::Action, description: 'Test', dueAt: CarbonImmutable::tomorrow()));
    $attempt = [(string) $scenario->first->id, (string) $scenario->couple->id, 'request_accept_condition', (string) $request->id, '1'];
    expect((new ConcurrentOperations)->run('agreement-worker.php', [$attempt, $attempt]))->toBe(['accepted', 'accepted']);
    expect($request->refresh()->status)->toBe(RequestStatus::Accepted);
    expect($request->events()->count())->toBe(3);
});

it('creates one request when two copies of the same submission arrive concurrently', function (): void {
    $scenario = AgreementScenario::create();
    $attempt = [(string) $scenario->first->id, (string) $scenario->couple->id, 'request_create', '1', '0'];
    expect((new ConcurrentOperations)->run('agreement-worker.php', [$attempt, $attempt]))->toBe(['accepted', 'accepted']);
    expect(PartnerRequest::query()->count())->toBe(1);
    expect(PartnerRequest::query()->sole()->events()->count())->toBe(1);
});
