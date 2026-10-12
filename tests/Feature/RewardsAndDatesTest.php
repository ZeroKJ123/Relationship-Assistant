<?php

declare(strict_types=1);

use App\Enums\NotificationKind;
use App\Enums\RequestType;
use App\Models\AppearanceSetting;
use App\Models\MemorableDate;
use App\Models\MemorableDateNotice;
use App\Models\NotificationDelivery;
use App\Models\PartnerRequest;
use App\Models\Reward;
use App\Models\User;
use App\Services\Appearance\AppearanceCatalog;
use App\Services\Couples\MemorableDates;
use App\Services\Notifications\DeliveryContent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\AgreementScenario;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $this->withoutVite();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 09:00:00', 'Europe/Minsk'));
});
afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('keeps a personal reward shop that only its owner edits and only the partner can browse', function (): void {
    $pair = AgreementScenario::create();
    $payload = ['title' => 'Массаж спины', 'description' => 'Полчаса', 'heart_price' => 15, 'operation_key' => Str::uuid()->toString(), 'expected_version' => 0];

    $this->asUser($pair->first)->post(route('rewards.store'), [...$payload, 'heart_price' => 0])->assertSessionHasErrors('heart_price');
    $this->post(route('rewards.store'), $payload)->assertRedirect(route('rewards.index'));
    $this->post(route('rewards.store'), $payload)->assertRedirect(route('rewards.index'));
    $reward = Reward::query()->sole();
    expect($reward->user_id)->toBe($pair->first->id)->and($reward->heart_price)->toBe(15);

    $this->get(route('rewards.index'))->assertOk()->assertSee('Массаж спины')->assertSee('Изменить')->assertDontSee('Купить');
    $this->asUser($pair->second)->get(route('rewards.index', ['owner_id' => $pair->first->id]))->assertOk()->assertSee('Массаж спины')->assertSee(route('requests.create', [$pair->couple, 'reward_id' => $reward->id]));
    $this->get(route('rewards.edit', $reward))->assertForbidden();
    $this->put(route('rewards.update', $reward), [...$payload, 'operation_key' => Str::uuid()->toString(), 'heart_price' => 1])->assertForbidden();
    $this->asUser(User::factory()->create())->get(route('rewards.index', ['owner_id' => $pair->first->id]))->assertForbidden();

    $this->asUser($pair->first)->delete(route('rewards.destroy', $reward), ['expected_version' => 0, 'confirmation' => 1])->assertRedirect(route('rewards.index'));
    $this->get(route('rewards.index'))->assertOk()->assertDontSee('Массаж спины');
});

it('buys a reward as a paid service request at the current shop price', function (): void {
    $pair = AgreementScenario::create();
    $reward = Reward::factory()->create(['user_id' => $pair->first->id, 'title' => 'Завтрак в постель', 'heart_price' => 12]);
    $dueAt = CarbonImmutable::now('Europe/Minsk')->addDay()->format('Y-m-d\TH:i');
    $payload = fn (int $price): array => ['reward_id' => $reward->id, 'type' => 'service', 'heart_price' => $price, 'description' => 'Завтрак в постель', 'due_at' => $dueAt, 'operation_key' => Str::uuid()->toString()];

    $this->asUser($pair->first)->get(route('requests.create', [$pair->couple, 'reward_id' => $reward->id]))->assertForbidden();
    $this->asUser($pair->second)->get(route('requests.create', [$pair->couple, 'reward_id' => $reward->id]))->assertOk()
        ->assertSee('name="reward_id" value="'.$reward->id.'"', false)->assertSee('name="heart_price" value="12"', false)->assertSee('Цена: 12 сердечек');
    $this->post(route('requests.store', $pair->couple), $payload(1))->assertSessionHasErrors('reward');
    expect(PartnerRequest::query()->count())->toBe(0);

    $this->post(route('requests.store', $pair->couple), $payload(12))->assertSessionHasNoErrors()->assertRedirect();
    $request = PartnerRequest::query()->sole();
    expect($request->type)->toBe(RequestType::Service)->and($request->heart_price)->toBe(12)->and($request->author_id)->toBe($pair->second->id)->and($request->recipient_id)->toBe($pair->first->id);

    $reward->forceFill(['archived_at' => now()])->save();
    $this->post(route('requests.store', $pair->couple), $payload(12))->assertSessionHasErrors('reward');
    $this->get(route('requests.create', [$pair->couple, 'reward_id' => $reward->id]))->assertForbidden();
});

it('shows an unaffordable reward as unavailable instead of offering to buy it', function (): void {
    $pair = AgreementScenario::create();
    $reward = Reward::factory()->create(['user_id' => $pair->first->id, 'heart_price' => 900]);

    $this->asUser($pair->second)->get(route('rewards.index', ['owner_id' => $pair->first->id]))->assertOk()
        ->assertSee('Не хватает')->assertDontSee(route('requests.create', [$pair->couple, 'reward_id' => $reward->id]));
});

it('counts down to anniversaries and added dates in the timezone of the couple', function (): void {
    $pair = AgreementScenario::create();
    $pair->couple->forceFill(['started_on' => '2024-10-17', 'married_on' => '2025-10-14'])->save();
    $date = fn (string $title, string $day, bool $isYearly): MemorableDate => MemorableDate::factory()->create(['couple_id' => $pair->couple->id, 'created_by_id' => $pair->first->id, 'title' => $title, 'occurs_on' => $day, 'is_yearly' => $isYearly]);
    $date('Прошедший концерт', '2026-10-13', false);
    $date('Поездка', '2026-10-15', false);
    $date('День рождения', '1996-10-13', true);
    $date('Високосный день', '2024-02-29', true);

    $upcoming = app(MemorableDates::class)->getUpcoming($pair->couple->refresh(), CarbonImmutable::now());

    expect($upcoming->map(fn ($item): array => [$item->title, $item->date->toDateString(), $item->daysLeft, $item->years])->all())->toBe([
        ['Годовщина свадьбы', '2026-10-14', 0, 1],
        ['Поездка', '2026-10-15', 1, null],
        ['Годовщина отношений', '2026-10-17', 3, 2],
        ['Високосный день', '2027-02-28', 137, 3],
        ['День рождения', '2027-10-13', 364, 31],
    ]);
    $this->asUser($pair->first)->get('/')->assertOk()->assertSeeInOrder(['Годовщина свадьбы', '1-я годовщина', 'сегодня']);
});

it('lets active partners add and delete dates of their own couple only', function (): void {
    $pair = AgreementScenario::create();
    $other = AgreementScenario::create();
    $payload = ['title' => 'Первая встреча', 'occurs_on' => '2024-12-01', 'is_yearly' => 1, 'operation_key' => Str::uuid()->toString()];

    $this->asUser($pair->first)->post(route('memorable_dates.store', $pair->couple), [...$payload, 'occurs_on' => 'вчера'])->assertSessionHasErrors('occurs_on');
    $this->post(route('memorable_dates.store', $pair->couple), $payload)->assertRedirect(route('memorable_dates.index', $pair->couple));
    $this->post(route('memorable_dates.store', $pair->couple), $payload)->assertRedirect();
    $stored = MemorableDate::query()->sole();
    $this->asUser($pair->second)->get(route('memorable_dates.index', $pair->couple))->assertOk()->assertSee('Первая встреча')->assertSee('2-я годовщина')->assertSee('Годовщина отношений');

    $this->asUser($other->first)->get(route('memorable_dates.index', $pair->couple))->assertForbidden();
    $this->post(route('memorable_dates.store', $pair->couple), [...$payload, 'operation_key' => Str::uuid()->toString()])->assertForbidden();
    $this->delete(route('memorable_dates.destroy', [$other->couple, $stored]), ['confirmation' => 1])->assertNotFound();
    $this->asUser($pair->second)->delete(route('memorable_dates.destroy', [$pair->couple, $stored]))->assertSessionHasErrors('confirmation');
    $this->delete(route('memorable_dates.destroy', [$pair->couple, $stored]), ['confirmation' => 1])->assertRedirect(route('memorable_dates.index', $pair->couple));
    expect(MemorableDate::query()->count())->toBe(0);
});

it('reminds both partners once three days before a date and on the day itself', function (): void {
    $pair = AgreementScenario::create();
    $pair->couple->forceFill(['started_on' => '2024-10-17'])->save();
    $pair->couple->members()->update(['joined_at' => CarbonImmutable::now()->subDays(5)]);
    MemorableDate::factory()->create(['couple_id' => $pair->couple->id, 'created_by_id' => $pair->first->id, 'title' => 'Концерт <b>', 'occurs_on' => '2026-10-14', 'is_yearly' => false]);
    MemorableDate::factory()->create(['couple_id' => $pair->couple->id, 'created_by_id' => $pair->first->id, 'title' => 'Нескоро', 'occurs_on' => '2026-10-20', 'is_yearly' => false]);

    $this->artisan('care:process-due')->assertSuccessful();
    $this->artisan('care:process-due')->assertSuccessful();

    expect(MemorableDateNotice::query()->count())->toBe(4)->and(MemorableDateNotice::query()->where('title', 'Нескоро')->count())->toBe(0);
    $deliveries = NotificationDelivery::query()->where('kind', NotificationKind::MemorableDate)->where('recipient_id', $pair->first->id)->orderBy('id')->get();
    expect($deliveries)->toHaveCount(2);
    $texts = $deliveries->map(fn (NotificationDelivery $delivery): ?string => app(DeliveryContent::class)->getText($delivery))->all();
    expect($texts)->toContain("🎉 <b>Сегодня: Концерт &lt;b&gt;</b>\n📅 14 октября", "🗓 <b>Через 3 дн.: Годовщина отношений</b>\n📅 17 октября\n2-я годовщина");

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 12:00:00', 'Europe/Minsk'));
    $this->artisan('care:process-due')->assertSuccessful();
    expect(MemorableDateNotice::query()->count())->toBe(4);
});

it('adds the rewards shortcut to a saved dashboard layout once', function (): void {
    $catalog = new AppearanceCatalog;
    $settings = $catalog->defaults();
    $settings['layout']['tiles'] = array_values(array_filter($settings['layout']['tiles'], fn (array $tile): bool => $tile['id'] !== 'rewards'));
    AppearanceSetting::query()->whereKey(AppearanceCatalog::SINGLETON_ID)->update(['settings' => $settings]);
    $migration = require collect(glob(database_path('migrations/*add_rewards_tile_to_appearance_settings.php')))->sole();

    $migration->up();
    $migration->up();

    $saved = AppearanceSetting::query()->findOrFail(AppearanceCatalog::SINGLETON_ID);
    expect($saved->revision)->toBe(1)->and(collect($saved->settings['layout']['tiles'])->last())->toBe($catalog->tile('rewards'));
    $pair = AgreementScenario::create();
    $this->asUser($pair->first)->get('/')->assertOk()->assertSee(sprintf('data-layout-tile="rewards" href="%s"', route('rewards.index', ['owner_id' => $pair->second->id])), false);
});
