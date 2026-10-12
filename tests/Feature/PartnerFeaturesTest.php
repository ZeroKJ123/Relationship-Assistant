<?php

declare(strict_types=1);

use App\Actions\Catalogs\CreateCar;
use App\Actions\Couples\EndCouple;
use App\Actions\Couples\UpdateRelationshipDates;
use App\Actions\Mood\AddMoodEmoji;
use App\Actions\Requests\CreatePartnerRequest;
use App\Actions\Requests\RatePartnerAct;
use App\Actions\Requests\ResolveCondition;
use App\Actions\Requests\RespondToRequest;
use App\Data\Catalogs\CarData;
use App\Data\Couples\RelationshipDatesData;
use App\Data\Requests\ActRatingData;
use App\Data\Requests\NewRequestData;
use App\Data\Requests\RequestResponseData;
use App\Enums\ConditionType;
use App\Enums\DeliveryStatus;
use App\Enums\MoodLevel;
use App\Enums\NotificationKind;
use App\Enums\RequestResponse;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\Car;
use App\Models\Duty;
use App\Models\MoodEntry;
use App\Models\NotificationDelivery;
use App\Models\SettlementMethod;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Services\Couples\CoupleTimeline;
use App\Services\Notifications\DeliveryContent;
use App\Services\Telegram\KeyboardFactory;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\AgreementScenario;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $this->withoutVite();
});
afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('requests and returns an act rating once without duties or heart operations', function (): void {
    $pair = AgreementScenario::create();
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Rating, 'Приготовил завтрак и убрал кухню', CarbonImmutable::now()->addDay()));
    $this->asUser($pair->second)->get(route('requests.show', [$pair->couple, $request]))->assertOk()->assertSee('Оценка поступка от 1 до 5')->assertDontSee('Согласиться');
    $payload = ['expected_version' => 0, 'rating' => 5, 'comment' => 'Было очень приятно', 'confirmation' => 1];
    $this->post(route('requests.rating', [$pair->couple, $request]), $payload)->assertRedirect();
    $this->post(route('requests.rating', [$pair->couple, $request]), $payload)->assertRedirect();
    expect($request->refresh()->rating)->toBe(5)->and($request->rating_comment)->toBe('Было очень приятно')->and($request->status)->toBe(RequestStatus::Accepted)->and($request->events()->where('type', 'rated')->count())->toBe(1)->and($request->duties()->count())->toBe(0);
    $this->asUser($pair->first)->get(route('requests.show', [$pair->couple, $request]))->assertSee('Оценка поступка: 5 из 5')->assertSee('Было очень приятно');
    expect(DB::table('partner_requests')->where('id', $request->id)->value('rating_comment'))->not->toBe('Было очень приятно');
});

it('rejects author ratings invalid scores expired ratings and generic acceptance', function (): void {
    $pair = AgreementScenario::create();
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Rating, 'Мой поступок', CarbonImmutable::now()->addHour()));
    $this->asUser($pair->first)->post(route('requests.rating', [$pair->couple, $request]), ['expected_version' => 0, 'rating' => 5, 'confirmation' => 1])->assertForbidden();
    $this->asUser($pair->second)->post(route('requests.rating', [$pair->couple, $request]), ['expected_version' => 0, 'rating' => 6, 'confirmation' => 1])->assertSessionHasErrors('rating');
    expect(fn () => app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(RequestResponse::Agree, 0)))->toThrow(ValidationException::class);
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addHour());
    expect(fn () => app(RatePartnerAct::class)->execute($pair->second, $pair->couple, $request, new ActRatingData(0, 4, null)))->toThrow(ValidationException::class);
    expect($request->refresh()->rating)->toBeNull();
});

it('keeps act ratings free from very wanted penalties', function (): void {
    $pair = AgreementScenario::create();
    expect(fn () => app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Rating, 'Оцени поступок', CarbonImmutable::now()->addDay(), isVeryWanted: true)))->toThrow(ValidationException::class);
});

it('offers an optional emoji after mood and shows only the partner mood on home', function (): void {
    $pair = AgreementScenario::create();
    MoodEntry::factory()->create(['couple_id' => $pair->couple->id, 'user_id' => $pair->first->id, 'level' => MoodLevel::Bad, 'emoji' => '🤒']);
    $this->asUser($pair->second)->post(route('mood.store', $pair->couple), ['level' => 'great', 'operation_key' => Str::uuid()->toString()])->assertRedirect();
    $entry = MoodEntry::query()->where('user_id', $pair->second->id)->sole();
    $this->get(route('mood.index', [$pair->couple, 'emoji_entry' => $entry->id]))->assertOk()->assertSee('Хотите дополнить настроение эмоджи?')->assertSee('Пропустить');
    $this->put(route('mood.emoji', [$pair->couple, $entry]), ['emoji' => '🥰'])->assertRedirect();
    $this->put(route('mood.emoji', [$pair->couple, $entry]), ['emoji' => '🥰'])->assertRedirect();
    $this->asUser($pair->first)->get('/')->assertOk()->assertSee('Настроение партнёра')->assertSee('🥰')->assertDontSee('🤒')->assertDontSee('@else')->assertDontSee('Пригласите партнёра в ваше пространство')->assertViewHas('dashboard', fn ($dashboard): bool => $dashboard->latestMoods->count() === 1 && $dashboard->latestMoods->sole()->user_id === $pair->second->id);
    expect(NotificationDelivery::query()->where('kind', NotificationKind::MoodEmoji)->count())->toBe(0);
    expect(NotificationDelivery::query()->where('kind', NotificationKind::MoodEntry)->where('source_id', $entry->id)->sole()->status)->toBe(DeliveryStatus::Pending);
    $this->put(route('mood.emoji', [$pair->couple, $entry]), ['emoji' => '😢'])->assertNotFound();
});

it('rejects old mood decorations and unsupported emoji', function (): void {
    $pair = AgreementScenario::create();
    $old = MoodEntry::factory()->create(['couple_id' => $pair->couple->id, 'user_id' => $pair->first->id]);
    MoodEntry::factory()->create(['couple_id' => $pair->couple->id, 'user_id' => $pair->first->id]);
    expect(fn () => app(AddMoodEmoji::class)->execute($pair->first, $pair->couple, $old, '😌'))->toThrow(ValidationException::class);
    $this->asUser($pair->first)->put(route('mood.emoji', [$pair->couple, $old]), ['emoji' => '<script>'])->assertSessionHasErrors('emoji');
});

it('saves optional marriage and displays calendar years months and days', function (): void {
    CarbonImmutable::setTestNow('2026-10-10 12:00:00');
    $pair = AgreementScenario::create();
    $this->asUser($pair->first)->patch(route('couples.dates', $pair->couple), ['started_on' => '2024-07-09', 'married_on' => '2025-08-20', 'expected_version' => 0])->assertRedirect();
    expect(app(CoupleTimeline::class)->getDuration($pair->couple->refresh()))->toBe('2 г. 3 мес.');
    $this->get('/')->assertOk()->assertSee('(2 г. 3 мес.)')->assertSee('Свадьба: 20.08.2025');
    $this->patch(route('couples.dates', $pair->couple), ['started_on' => '2024-07-09', 'married_on' => '2024-07-08', 'expected_version' => 1])->assertSessionHasErrors('married_on');
    $this->patch(route('couples.dates', $pair->couple), ['started_on' => '2026-10-11', 'expected_version' => 1])->assertSessionHasErrors('started_on');
    $this->patch(route('couples.dates', $pair->couple), ['started_on' => '2024-07-09', 'married_on' => '', 'expected_version' => 1])->assertRedirect();
    expect($pair->couple->refresh()->married_on)->toBeNull();
    $this->get('/')->assertDontSee('Свадьба:');
});

it('protects relationship dates from outsiders stale forms and ended couples', function (): void {
    $pair = AgreementScenario::create();
    $this->asUser(User::factory()->create())->patch(route('couples.dates', $pair->couple), ['started_on' => '2024-01-01', 'expected_version' => 0])->assertForbidden();
    app(UpdateRelationshipDates::class)->execute($pair->first, $pair->couple, new RelationshipDatesData('2024-01-01', null, 0));
    expect(fn () => app(UpdateRelationshipDates::class)->execute($pair->second, $pair->couple, new RelationshipDatesData('2023-01-01', null, 0)))->toThrow(ValidationException::class);
    app(EndCouple::class)->execute($pair->first, $pair->couple);
    expect(fn () => app(UpdateRelationshipDates::class)->execute($pair->first, $pair->couple, new RelationshipDatesData('2023-01-01', null, 1)))->toThrow(AuthorizationException::class);
});

it('creates a private car once and snapshots fuel payment without a reward', function (): void {
    $pair = AgreementScenario::create();
    $key = Str::uuid()->toString();
    $car = app(CreateCar::class)->execute($pair->second, new CarData('Toyota Corolla', 'petrol', $key));
    app(CreateCar::class)->execute($pair->second, new CarData('Toyota Corolla', 'petrol', $key));
    expect(Car::query()->count())->toBe(1);
    $this->asUser($pair->first)->get(route('cars.index'))->assertOk()->assertDontSee('Toyota Corolla');
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Service, 'Помоги с уборкой', CarbonImmutable::now()->addDay()));
    app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(RequestResponse::Conditional, 0, conditionType: ConditionType::Custom, description: 'Заправь на 20 литров', dueAt: CarbonImmutable::now()->addDay(), methodId: $car->settlement_method_id));
    $condition = $request->conditions()->sole();
    expect($condition->method_kind)->toBe('fuel')->and($condition->description)->toContain('Toyota Corolla', '20 литров');
    SettlementMethod::query()->findOrFail($car->settlement_method_id)->forceFill(['name' => 'Другая машина'])->save();
    expect($condition->refresh()->description)->toContain('Toyota Corolla');
    app(ResolveCondition::class)->execute($pair->first, $pair->couple, $request, 1, true);
    expect(Duty::query()->where('request_condition_id', $condition->id)->sole()->is_reward_eligible)->toBeFalse();
});

it('rejects use of another users car as a payment condition', function (): void {
    $pair = AgreementScenario::create();
    $car = app(CreateCar::class)->execute($pair->first, new CarData('Личная машина', 'diesel', Str::uuid()->toString()));
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Service, 'Просьба', CarbonImmutable::now()->addDay()));
    expect(fn () => app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(RequestResponse::Conditional, 0, conditionType: ConditionType::Custom, description: 'Заправка', dueAt: CarbonImmutable::now()->addDay(), methodId: $car->settlement_method_id)))->toThrow(ValidationException::class);
});

it('delivers detailed request condition and mood texts without generated links', function (): void {
    $pair = AgreementScenario::create();
    $account = TelegramAccount::factory()->create(['user_id' => $pair->second->id]);
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Service, 'Принеси чай с лимоном', CarbonImmutable::now()->addDay()));
    $delivery = NotificationDelivery::query()->sole();
    $text = app(DeliveryContent::class)->getText($delivery);
    expect($text)->toContain(sprintf("<b>%s просит</b>\nПринеси чай с лимоном", e($pair->first->name)), '🕒 до завтра')->not->toContain('http', 'Просьба обновлена', 'Вид просьбы', '\\n');
    $keyboard = DB::transaction(fn () => app(KeyboardFactory::class)->create($delivery, $account));
    foreach ($keyboard['inline_keyboard'] as $row) {
        expect($row[0])->not->toHaveKey('url');
    }
    app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(RequestResponse::Conditional, 0, conditionType: ConditionType::Kisses, description: 'Три поцелуя', dueAt: CarbonImmutable::now()->addDay()));
    $response = NotificationDelivery::query()->where('recipient_id', $pair->first->id)->sole();
    expect(app(DeliveryContent::class)->getText($response))->toContain('но с условием', '<i>Ваша просьба:</i> Принеси чай с лимоном', '<i>Условие:</i> Три поцелуя')->not->toContain('http');
    $entry = MoodEntry::factory()->create(['couple_id' => $pair->couple->id, 'user_id' => $pair->first->id, 'emoji' => '😊', 'level' => MoodLevel::Good]);
    $mood = NotificationDelivery::factory()->create(['couple_id' => $pair->couple->id, 'recipient_id' => $pair->second->id, 'kind' => NotificationKind::MoodEntry, 'source_id' => $entry->id]);
    expect(app(DeliveryContent::class)->getText($mood))->toBe(sprintf('🙂 <b>%s: хорошо</b> 😊', e($pair->first->name)));
});
