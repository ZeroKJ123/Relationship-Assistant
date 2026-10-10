<?php

declare(strict_types=1);

use App\Actions\Requests\CreatePartnerRequest;
use App\Data\Requests\NewRequestData;
use App\Enums\RequestType;
use App\Models\Car;
use App\Models\Movie;
use App\Models\MovieVote;
use App\Models\SettlementMethod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Support\AgreementScenario;
use Tests\Support\ConcurrentOperations;

uses(DatabaseMigrations::class);

it('accepts just one competing act rating and sends one response event', function (): void {
    $pair = AgreementScenario::create();
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Rating, 'Rate act', CarbonImmutable::tomorrow()));
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [[(string) $pair->second->id, (string) $pair->couple->id, 'act_rate', (string) $request->id, '4'], [(string) $pair->second->id, (string) $pair->couple->id, 'act_rate', (string) $request->id, '5']]);
    expect($results)->toContain('accepted', 'rejected');
    expect($request->refresh()->rating)->toBeIn([4, 5])->and($request->events()->where('type', 'rated')->count())->toBe(1);
});

it('preserves both simultaneous movie likes as one mutual match', function (): void {
    $pair = AgreementScenario::create();
    $movie = Movie::factory()->create(['user_id' => $pair->first->id]);
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [[(string) $pair->first->id, (string) $pair->couple->id, 'movie_vote', (string) $movie->id, '1'], [(string) $pair->second->id, (string) $pair->couple->id, 'movie_vote', (string) $movie->id, '1']]);
    expect($results)->toBe(['accepted', 'accepted'])->and(MovieVote::query()->count())->toBe(2);
});

it('creates only one car and settlement method from simultaneous retries', function (): void {
    $pair = AgreementScenario::create();
    $args = [(string) $pair->first->id, (string) $pair->couple->id, 'car_create', '42', '0'];
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [$args, $args]);
    expect($results)->toBe(['accepted', 'accepted'])->and(Car::query()->count())->toBe(1)->and(SettlementMethod::query()->where('kind', 'fuel')->count())->toBe(1);
});

it('prevents concurrent relationship date forms from overwriting each other', function (): void {
    $pair = AgreementScenario::create();
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [[(string) $pair->first->id, (string) $pair->couple->id, 'relationship_dates', '2023', '0'], [(string) $pair->second->id, (string) $pair->couple->id, 'relationship_dates', '2024', '0']]);
    expect($results)->toContain('accepted', 'rejected')->and($pair->couple->refresh()->dates_version)->toBe(1);
});
