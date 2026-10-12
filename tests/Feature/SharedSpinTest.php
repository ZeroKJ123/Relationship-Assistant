<?php

declare(strict_types=1);

use App\Actions\Duties\ManageChoreDraw;
use App\Actions\Movies\ManageMovieChoice;
use App\Enums\SelectionStatus;
use App\Models\Duty;
use App\Models\Movie;
use App\Models\User;
use App\Services\Realtime\SelectionPresence;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\AgreementScenario;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $this->withoutVite();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-12 12:00:00', 'UTC'));
});
afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('waits for both chore consents and page presence and keeps one shared result on repeated heartbeats', function (): void {
    $pair = AgreementScenario::create();
    $action = app(ManageChoreDraw::class);
    $draw = $action->create($pair->first, $pair->couple, 'Помыть пол', CarbonImmutable::now()->addDay(), Str::uuid()->toString());
    $action->answer($pair->first, $pair->couple, $draw, true);
    $action->answer($pair->second, $pair->couple, $draw, true);
    expect($draw->refresh()->executor_id)->toBeNull()->and(Duty::query()->count())->toBe(0);
    $url = route('selections.presence', [$pair->couple, 'chores', $draw->id]);
    $this->asUser($pair->first)->postJson($url)->assertOk()->assertJsonPath('status', 'waiting');
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addSeconds(7));
    $this->asUser($pair->second)->postJson($url)->assertOk()->assertJsonPath('status', 'waiting');
    $this->asUser($pair->first)->postJson($url)->assertOk()->assertJsonPath('status', 'matched')->assertJsonPath('spin_started_at', CarbonImmutable::now()->addSeconds(SelectionPresence::START_DELAY_SECONDS)->getTimestampMs());
    $executor = $draw->refresh()->executor_id;
    $this->postJson($url)->assertOk()->assertJsonPath('status', 'matched');
    expect($draw->refresh()->executor_id)->toBe($executor)->and(Duty::query()->count())->toBe(1);
    $this->asUser(User::factory()->create())->postJson($url)->assertForbidden();
});

it('requires both consents and presence for movie roulette and uses the same start time in both pages', function (): void {
    $pair = AgreementScenario::create();
    Movie::factory()->create(['user_id' => $pair->first->id]);
    $action = app(ManageMovieChoice::class);
    $choice = $action->create($pair->first, $pair->couple, 'roulette', Str::uuid()->toString());
    $url = route('selections.presence', [$pair->couple, 'movies', $choice->id]);
    $this->asUser($pair->first)->postJson($url)->assertJsonPath('status', 'waiting');
    $this->asUser($pair->second)->postJson($url)->assertJsonPath('status', 'waiting');
    $action->answer($pair->first, $pair->couple, $choice, true);
    $action->answer($pair->second, $pair->couple, $choice, true);
    $this->postJson($url)->assertJsonPath('status', 'matched');
    $stamp = $choice->refresh()->spin_started_at->getTimestampMs();
    $this->get(route('movies.choice', $pair->couple))->assertOk()->assertSee(sprintf('data-spin-start="%d"', $stamp), false);
    $this->asUser($pair->first)->get(route('movies.choice', $pair->couple))->assertOk()->assertSee(sprintf('data-spin-start="%d"', $stamp), false);
});

it('requires fresh consent for a legacy active movie roulette instead of leaving it stuck', function (): void {
    $pair = AgreementScenario::create();
    Movie::factory()->create(['user_id' => $pair->first->id]);
    $action = app(ManageMovieChoice::class);
    $choice = $action->create($pair->first, $pair->couple, 'roulette', Str::uuid()->toString());
    $choice->forceFill(['status' => SelectionStatus::Active])->save();
    $this->asUser($pair->first)->get(route('movies.choice', $pair->couple))->assertOk()->assertSee('Ждём согласия обоих');
    $action->answer($pair->first, $pair->couple, $choice, true);
    $action->answer($pair->second, $pair->couple, $choice, true);
    app(SelectionPresence::class)->touch($pair->first, $choice);
    app(SelectionPresence::class)->touch($pair->second, $choice);
    $action->draw($pair->first, $pair->couple, $choice);
    expect($choice->refresh()->status)->toBe(SelectionStatus::Matched);
});
