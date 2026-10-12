<?php

declare(strict_types=1);

use App\Actions\Couples\EndCouple;
use App\Actions\Movies\SaveMovie;
use App\Actions\Movies\VoteForMovie;
use App\Data\Catalogs\MovieData;
use App\Models\Movie;
use App\Models\MovieVote;
use App\Models\User;
use App\Services\Movies\MovieSelection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\AgreementScenario;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $this->withoutVite();
});

it('keeps a personal watch list with favorites encrypted and no duplicate films', function (): void {
    $owner = User::factory()->create();
    $this->asUser($owner)->post(route('movies.store'), ['title' => 'Интерстеллар', 'year' => 2014, 'description' => 'Посмотреть вместе', 'is_favorite' => 1])->assertRedirect();
    $this->post(route('movies.store'), ['title' => 'ИНТЕРСТЕЛЛАР', 'year' => 2014, 'description' => 'В выходные', 'is_favorite' => 1])->assertRedirect();
    $movie = Movie::query()->sole();
    expect($movie->is_favorite)->toBeTrue()->and($movie->description)->toBe('В выходные')->and($movie->getRawOriginal('title'))->not->toBe('ИНТЕРСТЕЛЛАР');
    $this->get(route('movies.index'))->assertOk()->assertSee('Любимый')->assertSee('ИНТЕРСТЕЛЛАР');
    $this->asUser(User::factory()->create())->get(route('movies.index'))->assertOk()->assertDontSee('ИНТЕРСТЕЛЛАР');
    $this->delete(route('movies.destroy', $movie))->assertNotFound();
    $this->asUser($owner)->delete(route('movies.destroy', $movie))->assertRedirect();
    expect($movie->refresh()->archived_at)->not->toBeNull();
});

it('shows a match only after both partners like the same film and makes votes idempotent', function (): void {
    $pair = AgreementScenario::create();
    $movie = app(SaveMovie::class)->execute($pair->first, new MovieData('Начало', 2010, null, true));
    $same = app(SaveMovie::class)->execute($pair->second, new MovieData('начало', 2010, null, false));
    expect(app(MovieSelection::class)->getPool($pair->first, $pair->couple)->count())->toBe(1);
    expect(app(VoteForMovie::class)->execute($pair->first, $pair->couple, $movie, true))->toBeFalse();
    expect(app(VoteForMovie::class)->execute($pair->second, $pair->couple, $same, true))->toBeTrue();
    app(VoteForMovie::class)->execute($pair->second, $pair->couple, $same, true);
    expect(MovieVote::query()->count())->toBe(2)->and(app(MovieSelection::class)->getMatches($pair->first, $pair->couple)->count())->toBe(1);
    $this->asUser($pair->first)->get(route('movies.choice', $pair->couple))->assertOk()->assertSee('Пригласить к свайпам')->assertDontSee('data-movie-swipe', false);
    expect(app(MovieSelection::class)->getNext($pair->first, $pair->couple))->toBeNull();
    expect(fn () => app(VoteForMovie::class)->execute($pair->first, $pair->couple, $movie, false))->toThrow(ValidationException::class);
});

it('does not match a skipped film and roulette draws from the chosen pool only', function (): void {
    $pair = AgreementScenario::create();
    $movie = app(SaveMovie::class)->execute($pair->first, new MovieData('Один фильм', null, null, false));
    app(VoteForMovie::class)->execute($pair->first, $pair->couple, $movie, false);
    app(VoteForMovie::class)->execute($pair->second, $pair->couple, $movie, true);
    expect(app(VoteForMovie::class)->draw($pair->first, $pair->couple, true))->toBeNull()->and(app(VoteForMovie::class)->draw($pair->first, $pair->couple, false)?->id)->toBe($movie->id);
    $this->asUser($pair->first)->post(route('movies.draw', $pair->couple), ['pool' => 'all'])->assertGone();
});

it('prevents foreign movies votes and access and removes archived titles from roulette', function (): void {
    $pair = AgreementScenario::create();
    $foreign = Movie::factory()->create();
    $this->asUser($pair->first)->post(route('movies.vote', [$pair->couple, $foreign]), ['liked' => 1])->assertNotFound();
    $this->asUser(User::factory()->create())->get(route('movies.choice', $pair->couple))->assertForbidden();
    $movie = app(SaveMovie::class)->execute($pair->first, new MovieData('Убранный фильм', null, null, false));
    app(SaveMovie::class)->archive($pair->first, $movie);
    expect(app(VoteForMovie::class)->draw($pair->first, $pair->couple, false))->toBeNull();
    $this->asUser($pair->second)->post(route('movies.vote', [$pair->couple, $movie]), ['liked' => 1])->assertNotFound();
    app(EndCouple::class)->execute($pair->first, $pair->couple);
    $this->post(route('movies.draw', $pair->couple), ['pool' => 'all'])->assertForbidden();
    app(SaveMovie::class)->execute($pair->first, new MovieData('Новый личный фильм после расставания', null, null, false));
    expect(app(MovieSelection::class)->getPool($pair->second, $pair->couple)->count())->toBe(0);
});
