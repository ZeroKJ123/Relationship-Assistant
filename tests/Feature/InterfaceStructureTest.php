<?php

declare(strict_types=1);

use App\Actions\Mood\CreateMoodPrompt;
use App\Actions\Profiles\SaveCompliments;
use App\Enums\DutyStatus;
use App\Enums\MoodLevel;
use App\Enums\RequestStatus;
use App\Models\AppearanceSetting;
use App\Models\Duty;
use App\Models\MoodEntry;
use App\Models\MoodPrompt;
use App\Models\PartnerRequest;
use App\Models\User;
use App\Services\Appearance\AppearanceCatalog;
use App\Services\Appearance\AppearanceStore;
use App\Services\Mood\MoodCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AgreementScenario;
use Tests\Support\DutyScenario;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $this->withoutVite();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 09:00:00', 'Europe/Minsk'));
});
afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('groups requests by progress and offers an answer only to the partner who owes it', function (): void {
    $pair = AgreementScenario::create();
    $request = fn (string $description, RequestStatus $status): PartnerRequest => PartnerRequest::factory()->create(['couple_id' => $pair->couple->id, 'author_id' => $pair->second->id, 'recipient_id' => $pair->first->id, 'description' => $description, 'status' => $status]);
    $request('Отклонённая просьба', RequestStatus::Declined);
    $request('Просьба ждёт ответа', RequestStatus::Pending);
    $accepted = $request('Принятая просьба', RequestStatus::Accepted);
    Duty::factory()->create(['partner_request_id' => $accepted->id, 'status' => DutyStatus::Pending]);

    $this->asUser($pair->first)->get(route('requests.index', $pair->couple))->assertOk()
        ->assertSeeInOrder(['Ждут ответа', 'Просьба ждёт ответа', 'Ответить', 'В работе', 'Принятая просьба', 'Завершённые', 'Отклонённая просьба']);
    $this->asUser($pair->second)->get(route('requests.index', $pair->couple))->assertOk()->assertSee('Просьба ждёт ответа')->assertDontSee('Ответить');
});

it('groups active duties by urgency in the timezone of the couple', function (): void {
    $pair = AgreementScenario::create();
    $duty = function (string $description, DutyStatus $status, CarbonImmutable $dueAt) use ($pair): void {
        $source = PartnerRequest::factory()->create(['couple_id' => $pair->couple->id, 'author_id' => $pair->second->id, 'recipient_id' => $pair->first->id, 'status' => RequestStatus::Accepted]);
        Duty::factory()->create(['partner_request_id' => $source->id, 'status' => $status, 'description' => $description, 'due_at' => $dueAt->utc(), 'timezone' => 'Europe/Minsk']);
    };
    $now = CarbonImmutable::now('Europe/Minsk');
    $duty('Дело на потом', DutyStatus::Pending, $now->addDays(20));
    $duty('Дело на неделе', DutyStatus::Pending, $now->addDays(3));
    $duty('Дело до полуночи', DutyStatus::Pending, $now->endOfDay());
    $duty('Дело завтра утром', DutyStatus::Pending, $now->addDay()->startOfDay());
    $duty('Просроченное дело', DutyStatus::Overdue, $now->subDay());

    $this->asUser($pair->first)->get(route('duties.index', $pair->couple))->assertOk()
        ->assertSeeInOrder(['Просрочено', 'Просроченное дело', 'Сегодня', 'Дело до полуночи', 'Ближайшие 7 дней', 'Дело завтра утром', 'Дело на неделе', 'Позже', 'Дело на потом']);
});

it('builds a monthly mood calendar from the last personal mark of each day', function (): void {
    $pair = AgreementScenario::create();
    $mark = fn (int $userId, MoodLevel $level, string $recordedAt): MoodEntry => MoodEntry::factory()->create(['couple_id' => $pair->couple->id, 'user_id' => $userId, 'level' => $level, 'recorded_at' => CarbonImmutable::parse($recordedAt, 'Europe/Minsk')->utc()]);
    $mark($pair->first->id, MoodLevel::Bad, '2026-10-01 00:30:00');
    $mark($pair->first->id, MoodLevel::Good, '2026-10-13 08:00:00');
    $mark($pair->first->id, MoodLevel::Low, '2026-10-13 21:00:00');
    $mark($pair->first->id, MoodLevel::Great, '2026-09-30 23:30:00');
    $mark($pair->second->id, MoodLevel::Great, '2026-10-02 10:00:00');

    $calendar = app(MoodCalendar::class)->getMonth($pair->couple, $pair->first, CarbonImmutable::now());

    expect($calendar['title'])->toBe('Октябрь 2026')->and($calendar['offset'])->toBe(3)->and($calendar['days'])->toHaveCount(31)
        ->and($calendar['days'][0]['level'])->toBe(MoodLevel::Bad)->and($calendar['days'][1]['level'])->toBeNull()
        ->and($calendar['days'][12]['level'])->toBe(MoodLevel::Low)->and($calendar['days'][13]['isToday'])->toBeTrue()
        ->and($calendar['marked'])->toBe(2)->and($calendar['hard'])->toBe(2);
    $this->asUser($pair->first)->get(route('mood.index', $pair->couple))->assertOk()->assertSee('Октябрь 2026')->assertSee('трудных дней');
});

it('offers quick due dates calculated in the timezone of the couple', function (): void {
    $pair = AgreementScenario::create();

    $this->asUser($pair->first)->get(route('requests.create', $pair->couple))->assertOk()
        ->assertViewHas('dueShortcuts', ['Сегодня' => '2026-10-14T21:00', 'Завтра' => '2026-10-15T09:00', 'Через неделю' => '2026-10-21T09:00'])
        ->assertSee('data-due-value="2026-10-14T21:00"', false);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 22:15:00', 'Europe/Minsk'));
    $this->get(route('requests.create', $pair->couple))->assertViewHas('dueShortcuts', fn (array $shortcuts): bool => $shortcuts['Сегодня'] === '2026-10-14T23:59');
});

it('adds the default theme to saved appearance settings without discarding custom themes', function (): void {
    $catalog = new AppearanceCatalog;
    $settings = $catalog->defaults();
    unset($settings['themes'][AppearanceCatalog::DEFAULT_THEME]);
    $settings['themes']['custom'] = array_replace($settings['themes']['classic'], ['name' => 'Своя тема']);
    $settings['active'] = 'custom';
    $settings['seasonal'] = true;
    AppearanceSetting::query()->whereKey(AppearanceCatalog::SINGLETON_ID)->update(['settings' => $settings, 'revision' => 4]);
    $migration = require database_path('migrations/2026_10_12_135203_add_latte_theme_to_appearance_settings.php');

    $migration->up();
    $migration->up();

    $saved = AppearanceSetting::query()->findOrFail(AppearanceCatalog::SINGLETON_ID);
    expect($saved->revision)->toBe(5)->and($saved->settings['active'])->toBe(AppearanceCatalog::DEFAULT_THEME)
        ->and($saved->settings['seasons']['autumn'])->toBe(AppearanceCatalog::DEFAULT_THEME)
        ->and($saved->settings['themes']['custom']['name'])->toBe('Своя тема')
        ->and($saved->settings['themes'][AppearanceCatalog::DEFAULT_THEME])->toBe($catalog->latteTheme());

    $migration->down();
    $restored = AppearanceSetting::query()->findOrFail(AppearanceCatalog::SINGLETON_ID)->settings;
    expect($restored['themes'])->not->toHaveKey(AppearanceCatalog::DEFAULT_THEME)->and($restored['active'])->toBe('classic')->and($restored['seasons']['autumn'])->toBe('autumn');
});

it('explains the role on a duty page and keeps the confirmation locked until the partner reports', function (): void {
    $scenario = DutyScenario::create();
    $author = $scenario->pair->first;
    $executor = $scenario->pair->second;
    $page = route('duties.show', [$scenario->pair->couple, $scenario->duty]);

    $this->asUser($author)->get($page)->assertOk()
        ->assertSee(sprintf('Это ваша просьба. Выполняет %s.', $executor->name))
        ->assertSee(sprintf('Кнопка станет доступна, когда %s отметит выполнение.', $executor->name))
        ->assertSee('aria-describedby="verify-unavailable"', false)
        ->assertSee('Отменить просьбу')
        ->assertDontSee('data-duty-confirmation', false)->assertDontSee('К просьбе и условиям')->assertDontSee('Все дела');
    $this->asUser($executor)->get($page)->assertOk()
        ->assertSee(sprintf('Вас просит %s. Выполняете вы.', $author->name))
        ->assertSee('data-duty-confirmation', false)->assertDontSee('verify-unavailable', false);
});

it('returns to the duty page after a cancellation proposed from it', function (): void {
    $scenario = DutyScenario::create();
    $other = DutyScenario::create();
    $cancel = route('requests.cancel', [$scenario->pair->couple, $scenario->request]);
    $payload = ['confirmation' => 1, 'expected_version' => $scenario->request->refresh()->version];

    $this->asUser($scenario->pair->first)->post($cancel, [...$payload, 'return_duty' => $scenario->duty->id])->assertRedirect(route('duties.show', [$scenario->pair->couple, $scenario->duty]));
    expect($scenario->request->refresh()->cancellation_proposed_by_id)->toBe($scenario->pair->first->id);
    $this->asUser($scenario->pair->second)->post($cancel, ['confirmation' => 1, 'expected_version' => $scenario->request->version, 'return_duty' => $other->duty->id])->assertRedirect(route('requests.show', [$scenario->pair->couple, $scenario->request]));
});

it('shows the partner mood month with the chosen day and offers a self question from the dashboard card', function (): void {
    $pair = AgreementScenario::create();
    MoodEntry::factory()->create(['couple_id' => $pair->couple->id, 'user_id' => $pair->second->id, 'level' => MoodLevel::Low, 'recorded_at' => CarbonImmutable::parse('2026-09-20 10:00:00', 'Europe/Minsk')->utc()]);
    MoodEntry::factory()->create(['couple_id' => $pair->couple->id, 'user_id' => $pair->first->id, 'level' => MoodLevel::Great, 'recorded_at' => CarbonImmutable::parse('2026-09-21 10:00:00', 'Europe/Minsk')->utc()]);

    $this->asUser($pair->first)->get(route('mood.day', [$pair->couple, 'date' => '2026-09-20']))->assertOk()
        ->assertSee('Сентябрь 2026')->assertSee('20 сентября 2026')->assertSee('Записей: 1')
        ->assertSee(route('mood.day', [$pair->couple, 'date' => '2026-10-01']))->assertDontSee('Указать своё настроение')
        ->assertViewHas('calendar', fn (array $calendar): bool => $calendar['days'][19]['isSelected'] && $calendar['days'][19]['level'] === MoodLevel::Low && $calendar['days'][20]['level'] === null && $calendar['marked'] === 1 && $calendar['next'] === '2026-10-01');
    $this->get(route('mood.day', $pair->couple))->assertOk()->assertViewHas('calendar', fn (array $calendar): bool => $calendar['next'] === null && $calendar['days'][13]['isSelected']);
    $this->get('/')->assertOk()->assertSee(sprintf('data-mood-self="%s"', route('mood.self.store', $pair->couple)), false);
});

it('opens the duty page from the request list once a request has duties', function (): void {
    $scenario = DutyScenario::create();
    $pending = PartnerRequest::factory()->create(['couple_id' => $scenario->pair->couple->id, 'author_id' => $scenario->pair->second->id, 'recipient_id' => $scenario->pair->first->id, 'status' => RequestStatus::Pending]);

    $this->asUser($scenario->pair->first)->get(route('requests.index', $scenario->pair->couple))->assertOk()
        ->assertSee(sprintf('href="%s"', route('duties.show', [$scenario->pair->couple, $scenario->duty])), false)
        ->assertDontSee(sprintf('href="%s"', route('requests.show', [$scenario->pair->couple, $scenario->request])), false)
        ->assertSee(sprintf('href="%s"', route('requests.show', [$scenario->pair->couple, $pending])), false);
});

it('shows a request page in the duty layout with the viewer role and current step', function (): void {
    $pair = AgreementScenario::create();
    $request = PartnerRequest::factory()->create(['couple_id' => $pair->couple->id, 'author_id' => $pair->second->id, 'recipient_id' => $pair->first->id, 'description' => 'Принеси плед', 'status' => RequestStatus::Pending]);
    $page = route('requests.show', [$pair->couple, $request]);

    $this->asUser($pair->first)->get($page)->assertOk()
        ->assertSee(sprintf('Вас просит %s. Ответ за вами.', $pair->second->name))
        ->assertSeeInOrder(['data-state="done"', 'Создано', 'data-state="current"', 'Ответ', 'Выполнение'], false)
        ->assertSee('Кто и кого просит')->assertSee('Согласиться');
    $this->asUser($pair->second)->get($page)->assertOk()->assertSee(sprintf('Это ваша просьба. Отвечает %s.', $pair->first->name))->assertDontSee('Подтверждаю выбранный ответ');
    $request->forceFill(['status' => RequestStatus::Declined])->save();
    $this->get($page)->assertOk()->assertDontSee('data-state="current"', false)->assertDontSee('data-state="done"', false);
});

it('lets a partner choose personal mood question times and schedules prompts only at them', function (): void {
    $pair = AgreementScenario::create();
    $pair->couple->members()->update(['joined_at' => CarbonImmutable::now()->subDays(2)]);
    $route = route('mood.schedule', $pair->couple);
    $payload = ['enabled' => 1, 'expected_version' => 0, 'confirmation' => 1];

    $this->asUser($pair->first)->put($route, [...$payload, 'times' => ['', '']])->assertSessionHasErrors('times');
    $this->put($route, [...$payload, 'times' => ['25:00']])->assertSessionHasErrors('times.0');
    $this->put($route, [...$payload, 'times' => ['01:00', '02:00', '03:00', '04:00', '05:00', '06:00', '07:00', '08:00', '08:30']])->assertSessionHasErrors('times');
    $this->put($route, [...$payload, 'times' => ['21:15', '08:45', '21:15', '']])->assertSessionHasNoErrors()->assertRedirect();

    $members = $pair->couple->members();
    expect((clone $members)->where('user_id', $pair->first->id)->sole()->mood_schedule_times)->toBe(['08:45', '21:15'])
        ->and((clone $members)->where('user_id', $pair->second->id)->sole()->mood_schedule_times)->toBeNull();
    $this->get(route('mood.index', $pair->couple))->assertOk()->assertSee('value="08:45"', false)->assertSee('value="21:15"', false);

    $this->artisan('care:process-due')->assertSuccessful();
    $own = MoodPrompt::query()->where('user_id', $pair->first->id)->sole();
    expect($own->scheduled_at->setTimezone('Europe/Minsk')->format('H:i'))->toBe('08:45')
        ->and(MoodPrompt::query()->where('user_id', $pair->second->id)->sole()->scheduled_at->setTimezone('Europe/Minsk')->format('H:i'))->toBe('09:00')
        ->and(app(CreateMoodPrompt::class)->execute($pair->first, $pair->couple, CarbonImmutable::parse('2026-10-14 09:00:00', 'Europe/Minsk')->utc()))->toBeNull();
});

it('offers reminder and compliment shortcuts on the dashboard and keeps compliments on their own page', function (): void {
    $pair = AgreementScenario::create();

    $this->asUser($pair->first)->get('/')->assertOk()
        ->assertSee(sprintf('data-layout-tile="reminder" href="%s"', route('reminders.create', $pair->couple)), false)
        ->assertSee(sprintf('data-layout-tile="compliments" href="%s"', route('profile.compliments.index')), false)
        ->assertSee('Напомнить')->assertSee('Комплименты');
    $this->get(route('profile.compliments.index'))->assertOk()->assertSee('Комплименты партнёру')->assertSee('data-repeat-editor', false);
    $this->get(route('profile.show'))->assertOk()->assertDontSee('Комплименты партнёру');
    $this->post(route('profile.compliments.store'), ['compliments' => ['Ты солнце', '']])->assertRedirect(route('profile.compliments.index'));
    expect($pair->first->refresh()->compliments)->toBe(['Ты солнце']);
    $this->asUser(User::factory()->create())->get('/')->assertOk()->assertDontSee('data-layout-tile="reminder"', false);
});

it('adds the new dashboard shortcuts to a saved layout once and keeps custom tiles', function (): void {
    $catalog = new AppearanceCatalog;
    $settings = $catalog->defaults();
    $settings['layout']['tiles'] = array_values(array_filter($settings['layout']['tiles'], fn (array $tile): bool => ! in_array($tile['id'], ['reminder', 'compliments'], true)));
    $settings['layout']['tiles'][0]['title'] = 'Наша кухня';
    AppearanceSetting::query()->whereKey(AppearanceCatalog::SINGLETON_ID)->update(['settings' => $settings, 'revision' => 2]);
    $migration = require database_path('migrations/2026_10_12_145211_add_reminder_and_compliment_tiles_to_appearance_settings.php');

    $migration->up();
    $migration->up();

    $saved = AppearanceSetting::query()->findOrFail(AppearanceCatalog::SINGLETON_ID);
    expect($saved->revision)->toBe(3)->and(array_column($saved->settings['layout']['tiles'], 'id'))->toBe(['kitchen', 'request', 'date', 'gift', 'movies', 'car', 'chores', 'rating', 'reminder', 'compliments'])
        ->and($saved->settings['layout']['tiles'][0]['title'])->toBe('Наша кухня')->and($saved->settings['layout']['tiles'][8])->toBe($catalog->tile('reminder'));
    $migration->down();
    expect(AppearanceSetting::query()->findOrFail(AppearanceCatalog::SINGLETON_ID)->settings['layout']['tiles'])->toHaveCount(8);
});

it('lets each user choose a personal theme that changes only their colours and decoration', function (): void {
    $pair = AgreementScenario::create();
    $catalog = new AppearanceCatalog;

    $this->asUser($pair->first)->get(route('profile.show'))->assertOk()->assertSeeInOrder(['Тема оформления', 'Тыквенный латте', 'Белая', 'Любовная', 'Цветочная']);
    $this->patch(route('profile.theme'), ['theme' => 'classic'])->assertSessionHasErrors('theme');
    $this->patch(route('profile.theme'), ['theme' => 'love'])->assertRedirect(route('profile.show'))->assertSessionHasNoErrors();
    expect($pair->first->refresh()->theme)->toBe('love')->and($pair->second->refresh()->theme)->toBeNull();

    $this->get('/')->assertOk()->assertSee(sprintf('--color-violet:%s', $catalog->personalThemes()['love']['primary']), false)->assertSee('data-decoration="hearts"', false)->assertSee('data-layout-tile="kitchen"', false);
    $this->asUser($pair->second)->get('/')->assertOk()->assertSee(sprintf('--color-violet:%s', $catalog->latteTheme()['primary']), false)->assertSee('data-decoration="leaves"', false);
});

it('falls back to the shared theme when a chosen personal theme no longer exists', function (): void {
    $user = User::factory()->create(['theme' => 'floral']);
    $settings = (new AppearanceCatalog)->defaults();
    unset($settings['themes']['floral']);
    AppearanceSetting::query()->whereKey(AppearanceCatalog::SINGLETON_ID)->update(['settings' => $settings]);

    $this->asUser($user)->get(route('profile.show'))->assertOk()->assertSee('data-decoration="leaves"', false)->assertDontSee('Цветочная');
    $migration = require database_path(sprintf('migrations/%s', basename((string) collect(glob(database_path('migrations/*add_personal_themes_to_appearance_settings.php')))->sole())));
    $migration->up();
    $migration->up();
    expect(AppearanceSetting::query()->findOrFail(AppearanceCatalog::SINGLETON_ID)->revision)->toBe(1);
    app(AppearanceStore::class)->invalidate();
    $this->get(route('profile.show'))->assertOk()->assertSee('data-decoration="flowers"', false);
});

it('shows the partner compliment in the greeting instead of a separate dashboard card', function (): void {
    $pair = AgreementScenario::create();
    app(SaveCompliments::class)->execute($pair->second, ['Ты мой уют']);

    $this->asUser($pair->first)->get('/')->assertOk()
        ->assertSeeInOrder(['id="greeting-title"', 'data-compliment', 'Ты мой уют', 'class="partner-summary"'], false)
        ->assertDontSee('Забота начинается с маленьких вещей')->assertDontSee('partner-compliment', false)->assertDontSee('Комплимент от партнёра');
    $this->asUser($pair->second)->get('/')->assertOk()->assertSeeInOrder(['data-compliment', 'Забота начинается с маленьких вещей', 'class="partner-summary"'], false);
});
