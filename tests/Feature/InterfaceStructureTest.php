<?php

declare(strict_types=1);

use App\Enums\DutyStatus;
use App\Enums\MoodLevel;
use App\Enums\RequestStatus;
use App\Models\AppearanceSetting;
use App\Models\Duty;
use App\Models\MoodEntry;
use App\Models\PartnerRequest;
use App\Services\Appearance\AppearanceCatalog;
use App\Services\Mood\MoodCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AgreementScenario;

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
