<?php

declare(strict_types=1);

use App\Enums\SelectionStatus;
use App\Models\ChoreDraw;
use App\Models\DateIdea;
use App\Models\MoodEntry;
use App\Models\PartnerRequest;
use App\Models\StreakRestore;
use App\Services\Couples\CoupleStreak;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\AgreementScenario;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $this->withoutVite();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 12:00:00', 'Europe/Minsk'));
});
afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/** Отмечает настроение указанных участников в перечисленные дни по времени Минска. */
function markMoods(AgreementScenario $pair, array $days, bool $isBoth = true): void
{
    foreach ($days as $day) {
        foreach ($isBoth ? [$pair->first, $pair->second] : [$pair->first] as $user) {
            MoodEntry::factory()->create(['couple_id' => $pair->couple->id, 'user_id' => $user->id, 'recorded_at' => CarbonImmutable::parse(sprintf('%s 23:30:00', $day), 'Europe/Minsk')->utc()]);
        }
    }
}

it('counts a day only when both partners marked their mood and keeps the streak alive until the day ends', function (): void {
    $pair = AgreementScenario::create();
    markMoods($pair, ['2026-10-11', '2026-10-12', '2026-10-13']);
    markMoods($pair, ['2026-10-10', '2026-10-14'], isBoth: false);

    $state = app(CoupleStreak::class)->get($pair->couple, $pair->first, CarbonImmutable::now());
    expect($state->days)->toBe(3)->and($state->level)->toBe(2)->and($state->isTodayComplete)->toBeFalse()
        ->and($state->hasActorMarked)->toBeTrue()->and($state->hasPartnerMarked)->toBeFalse()->and($state->getStatus())->toBe('ждём партнёра')->and($state->canRestore())->toBeFalse();
    expect(app(CoupleStreak::class)->get($pair->couple, $pair->second, CarbonImmutable::now())->getStatus())->toBe('ждём вас');

    MoodEntry::factory()->create(['couple_id' => $pair->couple->id, 'user_id' => $pair->second->id, 'recorded_at' => CarbonImmutable::now()->utc()]);
    expect(app(CoupleStreak::class)->get($pair->couple, $pair->first, CarbonImmutable::now())->days)->toBe(4);
    $this->asUser($pair->first)->get('/')->assertOk()->assertSeeInOrder(['data-flame="2"', 'Серия: 4 дн.', 'сегодня отмечено'], false);
});

it('raises the flame level with the length of the streak', function (int $days, int $level): void {
    expect(app(CoupleStreak::class)->getLevel($days))->toBe($level);
})->with([[0, 0], [1, 1], [2, 1], [3, 2], [6, 2], [7, 3], [29, 3], [30, 4], [99, 4], [100, 5], [400, 5]]);

it('restores a broken streak with the monthly allowance and refuses when the gap is too long', function (): void {
    $pair = AgreementScenario::create();
    markMoods($pair, ['2026-10-08', '2026-10-09', '2026-10-10', '2026-10-14']);
    $restore = route('streak.restore', $pair->couple);

    $state = app(CoupleStreak::class)->get($pair->couple, $pair->first, CarbonImmutable::now());
    expect($state->days)->toBe(1)->and($state->missedDays)->toBe(['2026-10-13', '2026-10-12', '2026-10-11'])->and($state->restoredDays)->toBe(7)->and($state->restoresLeft)->toBe(5);
    $this->asUser($pair->first)->get(route('streak.show', $pair->couple))->assertOk()->assertSee('Серия прервалась')->assertSee('5 из 5');
    $this->post($restore)->assertSessionHasErrors('confirmation');
    $this->post($restore, ['confirmation' => 1])->assertRedirect(route('streak.show', $pair->couple));

    $restored = app(CoupleStreak::class)->get($pair->couple, $pair->first, CarbonImmutable::now());
    expect($restored->days)->toBe(7)->and($restored->level)->toBe(3)->and($restored->restoresLeft)->toBe(2)->and($restored->canRestore())->toBeFalse()->and(StreakRestore::query()->count())->toBe(3);
    $this->post($restore, ['confirmation' => 1])->assertSessionHasErrors('streak');

    $long = AgreementScenario::create();
    markMoods($long, ['2026-10-07', '2026-10-14']);
    expect(app(CoupleStreak::class)->get($long->couple, $long->first, CarbonImmutable::now())->canRestore())->toBeFalse();
    $this->asUser($long->first)->post(route('streak.restore', $long->couple), ['confirmation' => 1])->assertSessionHasErrors('streak');
    $this->asUser($long->first)->post($restore, ['confirmation' => 1])->assertForbidden();
});

it('counts only restores made in the current month against the allowance', function (): void {
    $pair = AgreementScenario::create();
    StreakRestore::factory()->count(5)->sequence(fn ($sequence): array => ['restored_on' => sprintf('2026-09-%02d', $sequence->index + 1)])->create(['couple_id' => $pair->couple->id, 'user_id' => $pair->first->id, 'created_at' => CarbonImmutable::parse('2026-09-20 10:00:00', 'UTC')]);
    StreakRestore::factory()->create(['couple_id' => $pair->couple->id, 'user_id' => $pair->first->id, 'restored_on' => '2026-10-02', 'created_at' => CarbonImmutable::parse('2026-10-03 10:00:00', 'UTC')]);

    expect(app(CoupleStreak::class)->get($pair->couple, $pair->first, CarbonImmutable::now())->restoresLeft)->toBe(4);
});

it('spins the date wheel only with enough ideas and turns the picked idea into an invitation draft', function (): void {
    $pair = AgreementScenario::create();
    $other = AgreementScenario::create();
    $ideas = route('date_ideas.index', $pair->couple);
    $add = fn (string $title) => $this->post(route('date_ideas.store', $pair->couple), ['title' => $title, 'operation_key' => Str::uuid()->toString()]);

    $this->asUser($pair->first);
    $add('Пикник в парке')->assertRedirect($ideas);
    $this->get($ideas)->assertOk()->assertSee('Добавьте хотя бы две идеи')->assertDontSee('Крутить колесо');
    $this->post(route('date_ideas.spin', $pair->couple))->assertSessionHasErrors('title');
    $add('Настолки дома')->assertRedirect($ideas);

    $this->post(route('date_ideas.spin', $pair->couple))->assertRedirect($ideas)->assertSessionHas('picked_date_idea');
    $picked = DateIdea::query()->findOrFail(session('picked_date_idea'));
    $this->get($ideas)->assertOk()->assertSee(sprintf('Выпало: %s', $picked->title))->assertSee('movie-wheel--result', false)->assertSee(route('dates.create', [$pair->couple, 'idea_id' => $picked->id]));
    $this->get(route('dates.create', [$pair->couple, 'idea_id' => $picked->id]))->assertOk()->assertSee(sprintf('value="%s"', $picked->title), false);

    $this->asUser($other->first)->get($ideas)->assertForbidden();
    $this->get(route('dates.create', [$other->couple, 'idea_id' => $picked->id]))->assertOk()->assertDontSee($picked->title);
    $this->delete(route('date_ideas.destroy', [$other->couple, $picked]), ['confirmation' => 1])->assertNotFound();
    $this->asUser($pair->second)->delete(route('date_ideas.destroy', [$pair->couple, $picked]), ['confirmation' => 1])->assertRedirect($ideas);
    expect(DateIdea::query()->count())->toBe(1);
});

it('limits the wheel to a readable number of ideas', function (): void {
    $pair = AgreementScenario::create();
    DateIdea::factory()->count(12)->create(['couple_id' => $pair->couple->id, 'created_by_id' => $pair->first->id]);

    $this->asUser($pair->first)->post(route('date_ideas.store', $pair->couple), ['title' => 'Тринадцатая', 'operation_key' => Str::uuid()->toString()])->assertSessionHasErrors('title');
    expect(DateIdea::query()->count())->toBe(12);
});

it('repeats both partners around the chore wheel and stops on a sector of the executor', function (): void {
    $pair = AgreementScenario::create();
    $ids = [$pair->first->id, $pair->second->id];
    sort($ids);
    $request = PartnerRequest::factory()->create(['couple_id' => $pair->couple->id, 'author_id' => $ids[0], 'recipient_id' => $ids[1]]);
    $draw = ChoreDraw::factory()->create(['couple_id' => $pair->couple->id, 'first_user_id' => $ids[0], 'second_user_id' => $ids[1], 'status' => SelectionStatus::Matched, 'first_ready' => true, 'second_ready' => true, 'executor_id' => $ids[1], 'partner_request_id' => $request->id, 'spin_started_at' => now()->subMinute()]);

    $response = $this->asUser($pair->first)->get(route('chores.show', [$pair->couple, $draw]))->assertOk()->assertSee('--film-count:8', false);
    $response->assertViewHas('sectors', fn ($sectors): bool => $sectors->pluck('id')->all() === [$ids[0], $ids[1], $ids[0], $ids[1], $ids[0], $ids[1], $ids[0], $ids[1]]);
    $response->assertViewHas('rotation', function (float|int $rotation) use ($response): bool {
        $stoppedAt = (int) floor(fmod(360 - fmod($rotation, 360), 360) / 45);

        return $response->viewData('sectors')[$stoppedAt]->id === $response->viewData('executor')->id;
    });
    expect(substr_count($response->getContent(), 'class="wheel-label"'))->toBe(8);
    $response->assertSeeInOrder(['data-spin-outcome', 'Выполняет', 'data-spin-announcement', 'Колесо выбрало', 'Вернуться к колесу'], false);
});

it('keeps only the new draw form on the chore draw page', function (): void {
    $pair = AgreementScenario::create();
    ChoreDraw::factory()->create(['couple_id' => $pair->couple->id, 'first_user_id' => $pair->first->id, 'second_user_id' => $pair->second->id, 'description' => 'Старая жеребьёвка']);

    $this->asUser($pair->first)->get(route('chores.index', $pair->couple))->assertOk()->assertSee('Предложить жеребьёвку')->assertDontSee('Старая жеребьёвка');
});
