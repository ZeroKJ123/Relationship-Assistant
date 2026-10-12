<?php

declare(strict_types=1);

use App\Actions\Couples\EndCouple;
use App\Actions\Duties\ManageChoreDraw;
use App\Actions\Mood\AddMoodEmoji;
use App\Actions\Mood\RecordMood;
use App\Actions\Movies\ManageMovieChoice;
use App\Actions\Profiles\SaveCompliments;
use App\Data\Mood\MoodData;
use App\Enums\DeliveryStatus;
use App\Enums\DutyStatus;
use App\Enums\MoodLevel;
use App\Enums\MoodSource;
use App\Enums\NotificationKind;
use App\Enums\SelectionStatus;
use App\Models\Duty;
use App\Models\DutyReminderBatch;
use App\Models\MoodEntry;
use App\Models\Movie;
use App\Models\MovieChoice;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\Media\PrivatePhotos;
use App\Services\Mood\MoodDay;
use App\Services\Notifications\DeliveryContent;
use App\Services\Scheduling\DueCare;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\AgreementScenario;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $this->withoutVite();
});

it('keeps one mood notification when an emoji is added before or after delivery', function (DeliveryStatus $status): void {
    $pair = AgreementScenario::create();
    $entry = app(RecordMood::class)->execute($pair->first, $pair->couple, new MoodData(MoodLevel::Good, Str::uuid()->toString(), MoodSource::Web));
    $delivery = NotificationDelivery::query()->where('kind', NotificationKind::MoodEntry)->sole();
    $delivery->forceFill(['status' => $status])->save();
    app(AddMoodEmoji::class)->execute($pair->first, $pair->couple, $entry, '🥰');
    expect(NotificationDelivery::query()->count())->toBe(1)->and($delivery->refresh()->status)->toBe($status)->and($entry->refresh()->emoji)->toBe('🥰');
    if ($status === DeliveryStatus::Pending) {
        expect(DB::transaction(fn () => app(DeliveryContent::class)->getText($delivery)))->toContain('🥰');
    }
})->with([DeliveryStatus::Pending, DeliveryStatus::Sent]);

it('calculates a permanent daily average using the partner local calendar boundaries', function (): void {
    $pair = AgreementScenario::create();
    foreach ([['2026-01-01 20:59:59', 'bad'], ['2026-01-01 21:00:00', 'good'], ['2026-01-02 12:00:00', 'neutral'], ['2026-01-02 20:59:59', 'low'], ['2026-01-02 21:00:00', 'great']] as [$time,$level]) {
        MoodEntry::factory()->create(['couple_id' => $pair->couple->id, 'user_id' => $pair->second->id, 'level' => $level, 'recorded_at' => CarbonImmutable::parse($time, 'UTC'), 'emoji' => '🥰']);
    }
    MoodEntry::factory()->create(['couple_id' => $pair->couple->id, 'user_id' => $pair->first->id, 'recorded_at' => CarbonImmutable::parse('2026-01-02 12:00:00', 'UTC')]);
    expect(app(MoodDay::class)->getSummary($pair->couple, $pair->second->id, '2026-01-02'))->toBe(['count' => 3, 'average' => 2.0]);
    $this->asUser($pair->first)->get(route('mood.day', [$pair->couple, 'date' => '2026-01-02']))->assertOk()->assertSee('Среднее за день: 2 из 3')->assertSee('🥰')->assertSee('23:59')->assertSee('00:00');
    $this->get(route('mood.day', [$pair->couple, 'date' => 'invalid']))->assertSessionHasErrors('date');
    $this->asUser(User::factory()->create())->get(route('mood.day', $pair->couple))->assertForbidden();
});

it('allows a profile photo only to its owner and current partner', function (): void {
    Storage::fake(PrivatePhotos::DISK);
    $pair = AgreementScenario::create();
    Storage::disk(PrivatePhotos::DISK)->put('test.jpg', 'private');
    $pair->second->forceFill(['avatar_path' => 'test.jpg'])->save();
    $this->asUser($pair->first)->get(route('profiles.avatar', $pair->second))->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    $this->get('/')->assertOk()->assertSee(route('profiles.avatar', [$pair->second, 'v' => $pair->second->updated_at->timestamp]), false);
    $this->asUser(User::factory()->create())->get(route('profiles.avatar', $pair->second))->assertNotFound();
    app(EndCouple::class)->execute($pair->first, $pair->couple, $pair->couple->version);
    $this->asUser($pair->first)->get(route('profiles.avatar', $pair->second))->assertNotFound();
});

it('stores compliments privately and shows only the current partner messages with a fallback', function (): void {
    $pair = AgreementScenario::create();
    app(SaveCompliments::class)->execute($pair->second, ['Ты мой уют', 'Ты мой уют', '  ']);
    app(SaveCompliments::class)->execute($pair->first, ['Личный другой текст']);
    expect($pair->second->refresh()->compliments)->toBe(['Ты мой уют'])->and($pair->second->getRawOriginal('compliments'))->not->toContain('Ты мой уют');
    $this->asUser($pair->first)->get('/')->assertOk()->assertSee('Ты мой уют')->assertDontSee('Личный другой текст');
    $this->post(route('profile.compliments.store'), ['compliments' => [str_repeat('a', 301)]])->assertSessionHasErrors('compliments.0');
    app(SaveCompliments::class)->execute($pair->second, []);
    $this->get('/')->assertOk()->assertSee('Забота начинается с маленьких вещей')->assertDontSee('Ты мой уют');
});

it('schedules unfinished duty reminders once at each daytime slot and excludes completed work', function (int $hour): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse(sprintf('2026-10-12 %02d:00:00', $hour), 'Europe/Minsk'));
    $pair = AgreementScenario::create();
    $pair->couple->members()->update(['joined_at' => now()->subDay()]);
    foreach ([DutyStatus::Pending, DutyStatus::Reported, DutyStatus::Confirmed, DutyStatus::Cancelled] as $status) {
        Duty::factory()->create(['couple_id' => $pair->couple->id, 'executor_id' => $pair->first->id, 'reviewer_id' => $pair->second->id, 'status' => $status, 'description' => $status === DutyStatus::Pending ? 'Помыть посуду' : 'Не включать']);
    }
    app(DueCare::class)->execute();
    app(DueCare::class)->execute();
    expect(DutyReminderBatch::query()->count())->toBe(1);
    $delivery = NotificationDelivery::query()->where('kind', NotificationKind::DutyReminder)->sole();
    $text = DB::transaction(fn () => app(DeliveryContent::class)->getText($delivery));
    expect($text)->toContain('Помыть посуду')->not->toContain('Не включать');
    Duty::query()->where('status', DutyStatus::Pending)->update(['status' => DutyStatus::Confirmed]);
    expect(DB::transaction(fn () => app(DeliveryContent::class)->getText($delivery)))->toBeNull();
})->with([10, 15, 20]);

it('does not issue duty reminders overnight outside slots or without unfinished duties', function (int $hour): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse(sprintf('2026-10-12 %02d:00:00', $hour), 'Europe/Minsk'));
    $pair = AgreementScenario::create();
    $pair->couple->members()->update(['joined_at' => now()->subDay()]);
    Duty::factory()->create(['couple_id' => $pair->couple->id, 'executor_id' => $pair->first->id, 'reviewer_id' => $pair->second->id]);
    app(DueCare::class)->execute();
    expect(DutyReminderBatch::query()->count())->toBe(0);
})->with([0, 9, 11, 21]);

it('requires both film consents and finishes at the first mutual like without rerolling', function (): void {
    $pair = AgreementScenario::create();
    $first = Movie::factory()->create(['user_id' => $pair->first->id]);
    $other = Movie::factory()->create(['user_id' => $pair->second->id]);
    $action = app(ManageMovieChoice::class);
    $key = Str::uuid()->toString();
    $choice = $action->create($pair->first, $pair->couple, 'swipe', $key);
    expect($action->create($pair->first, $pair->couple, 'swipe', $key)->id)->toBe($choice->id);
    expect(fn () => $action->vote($pair->first, $pair->couple, $choice, $first, true))->toThrow(ValidationException::class);
    $this->asUser($pair->first)->patch(route('movies.selections.update', [$pair->couple, $choice]), ['command' => 'answer', 'answer' => 'accept'])->assertSessionHasErrors('confirmation');
    $action->answer($pair->first, $pair->couple, $choice, true);
    expect($choice->refresh()->status)->toBe(SelectionStatus::Waiting);
    $action->answer($pair->second, $pair->couple, $choice, true);
    expect($choice->refresh()->status)->toBe(SelectionStatus::Active);
    $action->vote($pair->first, $pair->couple, $choice, $first, true);
    $action->vote($pair->second, $pair->couple, $choice, $first, true);
    $action->vote($pair->first, $pair->couple, $choice, $other, true);
    expect($choice->refresh()->status)->toBe(SelectionStatus::Matched)->and($choice->selected_movie_id)->toBe($first->id)->and(count($choice->votes[$pair->first->id]))->toBe(1);
    $this->asUser($pair->first)->get(route('movies.choice', $pair->couple))->assertOk()->assertSee('У вас совпало желание!')->assertDontSee('data-movie-swipe', false);
});

it('exhausts film sessions without a match and protects declined sessions and foreign access', function (): void {
    $pair = AgreementScenario::create();
    $film = Movie::factory()->create(['user_id' => $pair->first->id]);
    $action = app(ManageMovieChoice::class);
    $choice = $action->create($pair->first, $pair->couple, 'swipe', Str::uuid()->toString());
    $action->answer($pair->first, $pair->couple, $choice, true);
    $action->answer($pair->second, $pair->couple, $choice, true);
    $action->vote($pair->first, $pair->couple, $choice, $film, false);
    $action->vote($pair->second, $pair->couple, $choice, $film, true);
    expect($choice->refresh()->status)->toBe(SelectionStatus::Exhausted);
    $next = $action->create($pair->first, $pair->couple, 'swipe', Str::uuid()->toString());
    $action->answer($pair->second, $pair->couple, $next, false);
    expect($next->refresh()->status)->toBe(SelectionStatus::Declined);
    expect(fn () => $action->answer(User::factory()->create(), $pair->couple, $next, true))->toThrow(AuthorizationException::class);
});

it('stores one shared roulette result chosen only from both partners film lists', function (): void {
    $pair = AgreementScenario::create();
    $a = Movie::factory()->create(['user_id' => $pair->first->id]);
    $b = Movie::factory()->create(['user_id' => $pair->second->id]);
    Movie::factory()->create();
    $action = app(ManageMovieChoice::class);
    $choice = $action->create($pair->first, $pair->couple, 'roulette', Str::uuid()->toString());
    $action->draw($pair->first, $pair->couple, $choice);
    $chosen = $choice->refresh()->selected_movie_id;
    $action->draw($pair->second, $pair->couple, $choice);
    expect($choice->refresh()->selected_movie_id)->toBe($chosen)->toBeIn([$a->id, $b->id]);
    $this->asUser($pair->second)->get(route('movies.choice', $pair->couple))->assertOk()->assertSee('Рулетка выбрала фильм');
});

it('validates film links and keeps uploaded posters private for the current couple', function (): void {
    Storage::fake(PrivatePhotos::DISK);
    $pair = AgreementScenario::create();
    $this->asUser($pair->first)->post(route('movies.store'), ['title' => 'Фильм', 'link' => 'https://example.org/film', 'photo' => UploadedFile::fake()->image('poster.png')])->assertRedirect();
    $movie = Movie::query()->sole();
    expect($movie->link)->toBe('https://example.org/film')->and($movie->photo_path)->not->toBeNull();
    $this->asUser($pair->second)->get(route('movies.photo', $movie))->assertOk();
    $this->asUser(User::factory()->create())->get(route('movies.photo', $movie))->assertNotFound();
    $this->post(route('movies.store'), ['title' => 'Плохая ссылка', 'link' => 'javascript:alert(1)'])->assertSessionHasErrors('link');
});

it('requires two chore consents and creates exactly one accepted duty for the selected executor', function (): void {
    $pair = AgreementScenario::create();
    $action = app(ManageChoreDraw::class);
    $key = Str::uuid()->toString();
    $draw = $action->create($pair->first, $pair->couple, 'Помыть пол', CarbonImmutable::now()->addDay(), $key);
    $action->answer($pair->first, $pair->couple, $draw, true);
    expect(Duty::query()->count())->toBe(0);
    $action->answer($pair->second, $pair->couple, $draw, true);
    $executor = $draw->refresh()->executor_id;
    $action->answer($pair->first, $pair->couple, $draw, true);
    $action->answer($pair->second, $pair->couple, $draw, false);
    expect($draw->refresh()->status)->toBe(SelectionStatus::Matched)->and($draw->executor_id)->toBe($executor)->and(Duty::query()->count())->toBe(1)->and(Duty::query()->sole()->executor_id)->toBe($executor)->and(Duty::query()->sole()->status)->toBe(DutyStatus::Pending);
    $this->asUser($pair->first)->get(route('chores.show', [$pair->couple, $draw]))->assertOk()->assertSee('Выполняет');
});

it('does not assign a chore after refusal and rejects missing web consent and foreign users', function (): void {
    $pair = AgreementScenario::create();
    $action = app(ManageChoreDraw::class);
    $draw = $action->create($pair->first, $pair->couple, 'Полить цветы', CarbonImmutable::now()->addDay(), Str::uuid()->toString());
    $this->asUser($pair->first)->patch(route('chores.update', [$pair->couple, $draw]), ['answer' => 'accept'])->assertSessionHasErrors('confirmation');
    $this->asUser($pair->second)->patch(route('chores.update', [$pair->couple, $draw]), ['answer' => 'decline'])->assertRedirect();
    expect($draw->refresh()->status)->toBe(SelectionStatus::Declined)->and(Duty::query()->count())->toBe(0);
    expect(fn () => $action->answer(User::factory()->create(), $pair->couple, $draw, true))->toThrow(AuthorizationException::class);
});

it('uses a twenty three hour local day for mood graphs across daylight saving time', function (): void {
    $pair = AgreementScenario::create();
    $pair->couple->forceFill(['timezone' => 'Europe/Warsaw'])->save();
    foreach (['2026-03-28 23:00:00', '2026-03-29 21:59:59', '2026-03-29 22:00:00'] as $time) {
        MoodEntry::factory()->create(['couple_id' => $pair->couple->id, 'user_id' => $pair->second->id, 'recorded_at' => CarbonImmutable::parse($time, 'UTC'), 'level' => MoodLevel::Good]);
    }
    $days = app(MoodDay::class);
    expect($days->getSummary($pair->couple, $pair->second->id, '2026-03-29'))->toBe(['count' => 2, 'average' => 3.0]);
    $chart = $days->getChart($pair->couple, $pair->second->id, '2026-03-29', 3.0);
    expect($chart['dots'])->toHaveCount(2)->and($chart['dots'][0]['x'])->toBe(20.0)->and($chart['dots'][1]['x'])->toBeLessThan(300.0)->and($chart['average_y'])->toBe(20.0);
});

it('completes film choices through validated web forms', function (): void {
    $pair = AgreementScenario::create();
    $film = Movie::factory()->create(['user_id' => $pair->first->id]);
    $this->asUser($pair->first)->post(route('movies.selections.store', $pair->couple), ['mode' => 'swipe', 'operation_key' => Str::uuid()->toString()])->assertRedirect();
    $choice = MovieChoice::query()->sole();
    $url = route('movies.selections.update', [$pair->couple, $choice]);
    $this->patch($url, ['command' => 'answer', 'answer' => 'accept', 'confirmation' => '1'])->assertRedirect();
    $this->asUser($pair->second)->patch($url, ['command' => 'answer', 'answer' => 'accept', 'confirmation' => '1'])->assertRedirect();
    $this->patch($url, ['command' => 'vote', 'movie_id' => $film->id, 'liked' => '1'])->assertRedirect();
    $this->asUser($pair->first)->patch($url, ['command' => 'vote', 'movie_id' => $film->id, 'liked' => '1'])->assertRedirect();
    expect($choice->refresh()->status)->toBe(SelectionStatus::Matched);
});

it('accepts fifty compliments plus the blank input from the profile form', function (): void {
    $pair = AgreementScenario::create();
    $this->asUser($pair->first);
    $texts = array_map(fn (int $i): string => sprintf('Комплимент %d', $i), range(1, 50));
    $this->post(route('profile.compliments.store'), ['compliments' => [...$texts, '']])->assertRedirect()->assertSessionHasNoErrors();
    expect($pair->first->refresh()->compliments)->toHaveCount(50);
});
