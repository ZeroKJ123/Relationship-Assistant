<?php

declare(strict_types=1);

use App\Actions\Duties\ManageChoreDraw;
use App\Actions\Movies\ManageMovieChoice;
use App\Enums\NotificationKind;
use App\Enums\SelectionStatus;
use App\Models\ChoreDraw;
use App\Models\Duty;
use App\Models\DutyReminderBatch;
use App\Models\Movie;
use App\Models\MovieChoice;
use App\Models\NotificationDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Tests\Support\AgreementScenario;
use Tests\Support\ConcurrentOperations;

uses(DatabaseMigrations::class);

it('starts a film session once when both confirmations arrive concurrently', function (): void {
    $pair = AgreementScenario::create();
    Movie::factory()->create(['user_id' => $pair->first->id]);
    $choice = app(ManageMovieChoice::class)->create($pair->first, $pair->couple, 'swipe', Str::uuid()->toString());
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [[(string) $pair->first->id, (string) $pair->couple->id, 'selection_answer', (string) $choice->id, '1'], [(string) $pair->second->id, (string) $pair->couple->id, 'selection_answer', (string) $choice->id, '1']]);
    expect($results)->toBe(['accepted', 'accepted'])->and($choice->refresh()->status)->toBe(SelectionStatus::Active)->and($choice->first_ready)->toBeTrue()->and($choice->second_ready)->toBeTrue();
});

it('keeps the first match when two different mutual likes arrive at the same time', function (): void {
    $pair = AgreementScenario::create();
    $films = Movie::factory()->count(2)->create(['user_id' => $pair->first->id]);
    $action = app(ManageMovieChoice::class);
    $choice = $action->create($pair->first, $pair->couple, 'swipe', Str::uuid()->toString());
    $action->answer($pair->first, $pair->couple, $choice, true);
    $action->answer($pair->second, $pair->couple, $choice, true);
    foreach ($films as $film) {
        $action->vote($pair->first, $pair->couple, $choice, $film, true);
    }
    (new ConcurrentOperations)->run('agreement-worker.php', $films->map(fn (Movie $film): array => [(string) $pair->second->id, (string) $pair->couple->id, 'selection_vote', (string) $choice->id, (string) $film->id])->all());
    expect($choice->refresh()->status)->toBe(SelectionStatus::Matched)->and($choice->selected_movie_id)->toBeIn($films->pluck('id')->all())->and(count($choice->votes[$pair->second->id]))->toBe(1);
});

it('draws a shared movie once despite concurrent spins', function (): void {
    $pair = AgreementScenario::create();
    Movie::factory()->count(3)->create(['user_id' => $pair->first->id]);
    $choice = app(ManageMovieChoice::class)->create($pair->first, $pair->couple, 'roulette', Str::uuid()->toString());
    (new ConcurrentOperations)->run('agreement-worker.php', [[(string) $pair->first->id, (string) $pair->couple->id, 'selection_draw', (string) $choice->id, '0'], [(string) $pair->second->id, (string) $pair->couple->id, 'selection_draw', (string) $choice->id, '0']]);
    $chosen = $choice->refresh()->selected_movie_id;
    expect($choice->status)->toBe(SelectionStatus::Matched)->and($chosen)->toBeIn($choice->pool);
    app(ManageMovieChoice::class)->draw($pair->first, $pair->couple, $choice);
    expect($choice->refresh()->selected_movie_id)->toBe($chosen);
});

it('creates one duty when two partners agree to a chore draw concurrently', function (): void {
    $pair = AgreementScenario::create();
    $draw = app(ManageChoreDraw::class)->create($pair->first, $pair->couple, 'Совместное дело', CarbonImmutable::now()->addDay(), Str::uuid()->toString());
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [[(string) $pair->first->id, (string) $pair->couple->id, 'chore_answer', (string) $draw->id, '1'], [(string) $pair->second->id, (string) $pair->couple->id, 'chore_answer', (string) $draw->id, '1']]);
    expect($results)->toBe(['accepted', 'accepted'])->and($draw->refresh()->status)->toBe(SelectionStatus::Matched)->and(Duty::query()->count())->toBe(1)->and(Duty::query()->sole()->executor_id)->toBe($draw->executor_id);
});

it('creates one unfinished duty reminder per slot despite concurrent scheduler ticks', function (): void {
    $pair = AgreementScenario::create();
    $slot = CarbonImmutable::parse('2026-10-12 10:00:00', 'Europe/Minsk');
    $pair->couple->members()->update(['joined_at' => $slot->subDay()]);
    Duty::factory()->create(['couple_id' => $pair->couple->id, 'executor_id' => $pair->first->id, 'reviewer_id' => $pair->second->id]);
    $arguments = [(string) $pair->first->id, (string) $pair->couple->id, 'care_process', '0', (string) $slot->timestamp];
    (new ConcurrentOperations)->run('agreement-worker.php', [$arguments, $arguments]);
    expect(DutyReminderBatch::query()->count())->toBe(1)->and(NotificationDelivery::query()->where('kind', NotificationKind::DutyReminder)->count())->toBe(1);
});

it('allows only one active film session when two invitations are created simultaneously', function (): void {
    $pair = AgreementScenario::create();
    Movie::factory()->create(['user_id' => $pair->first->id]);
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [[(string) $pair->first->id, (string) $pair->couple->id, 'selection_create', '1', '0'], [(string) $pair->second->id, (string) $pair->couple->id, 'selection_create', '2', '0']]);
    expect($results)->toContain('accepted', 'rejected')->and(MovieChoice::query()->count())->toBe(1);
});

it('deduplicates concurrent chore proposals with the same operation key', function (): void {
    $pair = AgreementScenario::create();
    $arguments = [(string) $pair->first->id, (string) $pair->couple->id, 'chore_create', '1', (string) CarbonImmutable::now()->addDay()->timestamp];
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [$arguments, $arguments]);
    expect($results)->toBe(['accepted', 'accepted'])->and(ChoreDraw::query()->count())->toBe(1);
});
