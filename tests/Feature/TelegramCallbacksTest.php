<?php

declare(strict_types=1);

use App\Actions\Couples\Agreements\AcceptProposal;
use App\Actions\Couples\EndCouple;
use App\Actions\Duties\ReportDuty;
use App\Actions\Money\CreateMoneyRequest;
use App\Actions\Mood\CreateMoodPrompt;
use App\Actions\Mood\RecordMood;
use App\Actions\Mood\RequestPartnerMood;
use App\Actions\Requests\CreatePartnerRequest;
use App\Actions\Requests\RespondToRequest;
use App\Data\Money\MoneyRequestData;
use App\Data\Mood\MoodData;
use App\Data\Requests\NewRequestData;
use App\Data\Requests\RequestResponseData;
use App\Enums\ConditionType;
use App\Enums\Currency;
use App\Enums\DutyStatus;
use App\Enums\MoodLevel;
use App\Enums\MoodSource;
use App\Enums\NotificationKind;
use App\Enums\RequestResponse;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Enums\TelegramActionStage;
use App\Enums\TelegramOperation;
use App\Models\DutyConfirmation;
use App\Models\HeartTransaction;
use App\Models\MoodEntry;
use App\Models\NotificationDelivery;
use App\Models\TelegramAccount;
use App\Models\TelegramCallbackAction;
use App\Models\TelegramUpdateReceipt;
use App\Services\Notifications\DeliveryLifecycle;
use App\Services\Telegram\KeyboardFactory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\AgreementScenario;
use Tests\Support\DutyScenario;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['services.telegram.enabled' => true, 'services.telegram.bot_token' => '123456789:fake-test-token', 'services.telegram.bot_username' => 'HomeAssistTestBot', 'services.telegram.webhook_secret' => str_repeat('test-secret-', 4)]);
    Http::fake(['*' => Http::response(['ok' => true, 'result' => true])]);
});

/** @return array<string, mixed> */
function telegramCallbackPayload(int $id, string $telegramId, string $data): array
{
    return ['update_id' => $id, 'callback_query' => ['id' => sprintf('query-%d', $id), 'from' => ['id' => (int) $telegramId, 'is_bot' => false, 'first_name' => 'Анна'], 'message' => ['message_id' => 1, 'chat' => ['id' => (int) $telegramId, 'type' => 'private']], 'data' => $data]];
}

function emojiResponse(TestCase $test, int $id, TelegramAccount $account, string $text): TestResponse
{
    $payload = ['update_id' => $id, 'message' => ['message_id' => $id, 'from' => ['id' => (int) $account->telegram_id, 'is_bot' => false, 'first_name' => 'Анна'], 'chat' => ['id' => (int) $account->telegram_id, 'type' => 'private'], 'text' => $text]];

    return $test->postJson(route('telegram.webhook'), $payload, ['X-Telegram-Bot-Api-Secret-Token' => config('services.telegram.webhook_secret')])->assertOk();
}

function callbackResponse(TestCase $test, int $id, TelegramAccount $account, string $data): TestResponse
{
    return $test->postJson(route('telegram.webhook'), telegramCallbackPayload($id, $account->telegram_id, $data), ['X-Telegram-Bot-Api-Secret-Token' => config('services.telegram.webhook_secret')])->assertOk();
}

it('applies a request on the first click and deduplicates updates and repeated clicks', function (): void {
    $pair = AgreementScenario::create();
    $account = TelegramAccount::factory()->create(['user_id' => $pair->second->id]);
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Service, 'Помощь', CarbonImmutable::now()->addDay()));
    $button = DB::transaction(fn (): array => app(KeyboardFactory::class)->createButton($account, $pair->couple->id, 'request', $request->id, 0, TelegramOperation::Agree));
    $reply = callbackResponse($this, 10, $account, $button['callback_data'])->json();
    expect($request->refresh()->status)->toBe(RequestStatus::Accepted)->and($request->duties()->count())->toBe(1);
    callbackResponse($this, 10, $account, $button['callback_data'])->assertExactJson($reply);
    expect(TelegramCallbackAction::query()->count())->toBe(1);
    $confirm = $button['callback_data'];
    $done = callbackResponse($this, 11, $account, $confirm)->json();
    callbackResponse($this, 11, $account, $confirm)->assertExactJson($done);
    callbackResponse($this, 12, $account, $confirm)->assertJsonFragment(['text' => 'Эта кнопка уже использована. Проверьте результат в приложении.']);
    expect($request->refresh()->status)->toBe(RequestStatus::Accepted)->and($request->version)->toBe(1)->and($request->duties()->count())->toBe(1)->and($request->events()->count())->toBe(2);
    expect(TelegramUpdateReceipt::query()->count())->toBe(3);
});

it('cancels a confirmation without changing the request or heart balance', function (): void {
    $pair = AgreementScenario::create();
    $account = TelegramAccount::factory()->create(['user_id' => $pair->second->id]);
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Service, 'Помощь', CarbonImmutable::now()->addDay()));
    $button = app(KeyboardFactory::class)->createButton($account, $pair->couple->id, 'request', $request->id, 0, TelegramOperation::Decline);
    TelegramCallbackAction::query()->sole()->forceFill(['stage' => TelegramActionStage::Confirm])->save();
    $cancel = str_replace('act:', 'cancel:', $button['callback_data']);
    callbackResponse($this, 21, $account, $cancel)->assertJsonFragment(['text' => 'Действие отменено.']);
    callbackResponse($this, 22, $account, str_replace('cancel:', 'act:', $cancel));
    expect($request->refresh()->status)->toBe(RequestStatus::Pending)->and(HeartTransaction::query()->where('type', '!=', 'starting_bonus')->count())->toBe(0)->and(TelegramCallbackAction::query()->latest('id')->first()->stage)->toBe(TelegramActionStage::Cancelled);
});

it('rejects foreign forged expired and wrong-role callbacks', function (string $case): void {
    $pair = AgreementScenario::create();
    $account = TelegramAccount::factory()->create(['user_id' => $pair->second->id]);
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Service, 'Помощь', CarbonImmutable::now()->addDay()));
    $button = app(KeyboardFactory::class)->createButton($account, $pair->couple->id, 'request', $request->id, 0, TelegramOperation::Agree);
    if ($case === 'foreign') {
        $account = TelegramAccount::factory()->create(['user_id' => $pair->first->id]);
    }
    if ($case === 'forged') {
        $button['callback_data'] = sprintf('act:%s', str_repeat('x', 40));
    }
    if ($case === 'expired') {
        $this->travelTo(TelegramCallbackAction::query()->sole()->expires_at);
    }
    if ($case === 'wrong_role') {
        TelegramCallbackAction::query()->sole()->forceFill(['operation' => TelegramOperation::AcceptCondition])->save();
    }
    callbackResponse($this, 30, $account, $button['callback_data'])->assertJsonMissingPath('reply_markup');
    expect($request->refresh()->status)->toBe(RequestStatus::Pending)->and($request->events()->count())->toBe(1);
})->with(['foreign', 'forged', 'expired', 'wrong_role']);

it('rejects confirmations after the website changed the state or the couple ended', function (string $case): void {
    $pair = AgreementScenario::create();
    $account = TelegramAccount::factory()->create(['user_id' => $pair->second->id]);
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Service, 'Помощь', CarbonImmutable::now()->addDay()));
    $button = app(KeyboardFactory::class)->createButton($account, $pair->couple->id, 'request', $request->id, 0, TelegramOperation::Agree);
    if ($case === 'website') {
        app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(RequestResponse::Agree, 0));
    }
    if ($case === 'ended') {
        app(EndCouple::class)->execute($pair->first, $pair->couple);
    }
    callbackResponse($this, 41, $account, $button['callback_data'])->assertJsonMissingPath('reply_markup');
    expect($request->refresh()->status)->toBe($case === 'website' ? RequestStatus::Accepted : RequestStatus::Pending)->and($request->version)->toBe($case === 'website' ? 1 : 0);
})->with(['website', 'ended']);

it('uses shared refusal penalties and the no-food-money exception once', function (TelegramOperation $operation, RequestType $type, int $penalty): void {
    $pair = AgreementScenario::create();
    $account = TelegramAccount::factory()->create(['user_id' => $pair->second->id]);
    $proposal = $pair->answerBoth(2, 2);
    app(AcceptProposal::class)->execute($pair->first, $pair->couple, $proposal);
    app(AcceptProposal::class)->execute($pair->second, $pair->couple, $proposal);
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData($type, 'Помощь', CarbonImmutable::now()->addDay(), isVeryWanted: true));
    $button = app(KeyboardFactory::class)->createButton($account, $pair->couple->id, 'request', $request->id, 0, $operation);
    $reply = callbackResponse($this, 50, $account, $button['callback_data'])->json();
    $confirm = $button['callback_data'];
    callbackResponse($this, 51, $account, $confirm);
    callbackResponse($this, 52, $account, $confirm);
    expect($request->refresh()->status)->toBe(RequestStatus::Declined)->and($request->decline_penalty)->toBe($penalty)->and(HeartTransaction::query()->where('type', '!=', 'starting_bonus')->count())->toBe($penalty > 0 ? 1 : 0);
})->with([[TelegramOperation::Decline, RequestType::Service, 15], [TelegramOperation::NoFoodMoney, RequestType::Food, 0]]);

it('accepts a condition independently through the shared action', function (): void {
    $pair = AgreementScenario::create();
    $account = TelegramAccount::factory()->create(['user_id' => $pair->first->id]);
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Service, 'Помощь', CarbonImmutable::now()->addDay()));
    app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(RequestResponse::Conditional, 0, conditionType: ConditionType::Custom, description: 'Встречная помощь', dueAt: CarbonImmutable::now()->addDay()));
    $button = app(KeyboardFactory::class)->createButton($account, $pair->couple->id, 'request', $request->id, 1, TelegramOperation::AcceptCondition);
    $reply = callbackResponse($this, 60, $account, $button['callback_data'])->json();
    callbackResponse($this, 61, $account, $button['callback_data']);
    expect($request->refresh()->status)->toBe(RequestStatus::Accepted)->and($request->duties()->count())->toBe(2)->and(HeartTransaction::query()->where('type', '!=', 'starting_bonus')->count())->toBe(0);
});

it('reports and verifies a duty with a single reward even after repeated button presses', function (): void {
    $scenario = DutyScenario::create();
    $executor = TelegramAccount::factory()->create(['user_id' => $scenario->pair->second->id]);
    $reviewer = TelegramAccount::factory()->create(['user_id' => $scenario->pair->first->id]);
    $button = app(KeyboardFactory::class)->createButton($executor, $scenario->pair->couple->id, 'duty', $scenario->duty->id, 0, TelegramOperation::Done);
    $reply = callbackResponse($this, 70, $executor, $button['callback_data'])->json();
    callbackResponse($this, 71, $executor, $button['callback_data']);
    expect($scenario->duty->refresh()->status)->toBe(DutyStatus::Reported)->and(HeartTransaction::query()->where('type', '!=', 'starting_bonus')->count())->toBe(0);
    $button = app(KeyboardFactory::class)->createButton($reviewer, $scenario->pair->couple->id, 'duty', $scenario->duty->id, 1, TelegramOperation::Verify);
    $reply = callbackResponse($this, 72, $reviewer, $button['callback_data'])->json();
    $confirm = $button['callback_data'];
    callbackResponse($this, 73, $reviewer, $confirm);
    callbackResponse($this, 74, $reviewer, $confirm);
    expect($scenario->duty->refresh()->status)->toBe(DutyStatus::Confirmed)->and(DutyConfirmation::query()->count())->toBe(2)->and(HeartTransaction::query()->where('type', '!=', 'starting_bonus')->count())->toBe(1);
});

it('records Telegram mood immediately and offers an optional emoji without confirmation', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-11 06:10:00', 'UTC'));
    $pair = AgreementScenario::create();
    $pair->couple->members()->update(['joined_at' => CarbonImmutable::now()->subDay()]);
    $account = TelegramAccount::factory()->create(['user_id' => $pair->first->id]);
    $prompt = app(CreateMoodPrompt::class)->execute($pair->first, $pair->couple, CarbonImmutable::parse('2026-10-11 06:00:00', 'UTC'));
    $button = app(KeyboardFactory::class)->createButton($account, $pair->couple->id, 'mood', $prompt->id, 1, TelegramOperation::MoodGood);
    $reply = callbackResponse($this, 80, $account, $button['callback_data'])->json();
    expect(MoodEntry::query()->count())->toBe(1);
    expect($reply['text'])->toContain('Ответьте одним эмоджи или стикером');
    expect($reply['reply_markup']['force_reply'])->toBeTrue();
    callbackResponse($this, 80, $account, $button['callback_data'])->assertExactJson($reply);
    emojiResponse($this, 79, $account, 'Просто текст')->assertJsonPath('reply_markup.force_reply', true);
    $saved = emojiResponse($this, 81, $account, '🫠')->assertJsonMissingPath('reply_markup')->json();
    emojiResponse($this, 81, $account, '🫠')->assertExactJson($saved);
    emojiResponse($this, 82, $account, '😎');
    callbackResponse($this, 83, $account, $button['callback_data']);
    expect(MoodEntry::query()->sole()->emoji)->toBe('🫠');
    expect(NotificationDelivery::query()->where('kind', NotificationKind::MoodEmoji)->count())->toBe(0);
    expect(MoodEntry::query()->sole()->source)->toBe(MoodSource::Telegram)->and($prompt->refresh()->answered_at)->not->toBeNull();
});

it('attaches short opaque buttons to notification deliveries with no personal text in action records', function (): void {
    $pair = AgreementScenario::create();
    TelegramAccount::factory()->create(['user_id' => $pair->second->id]);
    app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Service, 'Очень личная просьба', CarbonImmutable::now()->addDay()));
    $message = app(DeliveryLifecycle::class)->claim(NotificationDelivery::query()->sole());
    expect($message->keyboard['inline_keyboard'])->toHaveCount(1)->and($message->keyboard['inline_keyboard'][0])->toHaveCount(2);
    foreach ($message->keyboard['inline_keyboard'] as $row) {
        if (! isset($row[0]['callback_data'])) {
            continue;
        }
        expect(strlen($row[0]['callback_data']))->toBeLessThanOrEqual(64)->and($row[0]['callback_data'])->not->toContain('Очень личная просьба');
    }
    expect(TelegramCallbackAction::query()->firstOrFail()->toJson())->not->toContain('Очень личная просьба');
});

it('records a negative report or dispute through the shared duty rules', function (string $case): void {
    $scenario = DutyScenario::create();
    $actor = $case === 'not_done' ? $scenario->pair->second : $scenario->pair->first;
    $account = TelegramAccount::factory()->create(['user_id' => $actor->id]);
    if ($case === 'dispute') {
        app(ReportDuty::class)->execute($scenario->pair->second, $scenario->pair->couple, $scenario->duty, 0, true);
    }
    $button = app(KeyboardFactory::class)->createButton($account, $scenario->pair->couple->id, 'duty', $scenario->duty->id, $case === 'dispute' ? 1 : 0, $case === 'dispute' ? TelegramOperation::Dispute : TelegramOperation::NotDone);
    $reply = callbackResponse($this, 90, $account, $button['callback_data'])->json();
    $confirm = $button['callback_data'];
    callbackResponse($this, 91, $account, $confirm);
    callbackResponse($this, 92, $account, $confirm);
    expect($scenario->duty->refresh()->status)->toBe($case === 'dispute' ? DutyStatus::Disputed : DutyStatus::Pending);
    expect(HeartTransaction::query()->where('type', 'reward')->count())->toBe(0);
    expect(HeartTransaction::query()->where('type', 'false_report_penalty')->count())->toBe($case === 'dispute' ? 1 : 0);
})->with(['not_done', 'dispute']);

it('marks a money request paid without issuing hearts or exposing payment details in its buttons', function (): void {
    $pair = AgreementScenario::create();
    $pair->first->forceFill(['payment_details' => 'Private bank details'])->save();
    $account = TelegramAccount::factory()->create(['user_id' => $pair->second->id]);
    $request = app(CreateMoneyRequest::class)->execute($pair->first, $pair->couple, new MoneyRequestData('12.30', Currency::Byn, 'Помощь деньгами', CarbonImmutable::tomorrow(), Str::uuid()->toString()));
    app(RespondToRequest::class)->execute($pair->second, $pair->couple, $request, new RequestResponseData(RequestResponse::Agree, 0));
    $delivery = NotificationDelivery::query()->where('recipient_id', $pair->second->id)->sole();
    $keyboard = app(KeyboardFactory::class)->create($delivery, $account);
    expect($keyboard['inline_keyboard'][0][0]['text'])->toContain('Оплачено')->not->toContain('Private bank details');
    $reply = callbackResponse($this, 100, $account, $keyboard['inline_keyboard'][0][0]['callback_data'])->json();
    callbackResponse($this, 101, $account, $keyboard['inline_keyboard'][0][0]['callback_data']);
    expect($request->duties()->sole()->status)->toBe(DutyStatus::Reported);
    $reviewer = TelegramAccount::factory()->create(['user_id' => $pair->first->id]);
    $duty = $request->duties()->sole();
    $button = app(KeyboardFactory::class)->createButton($reviewer, $pair->couple->id, 'duty', $duty->id, $duty->version, TelegramOperation::Verify);
    $reply = callbackResponse($this, 102, $reviewer, $button['callback_data'])->json();
    callbackResponse($this, 103, $reviewer, $button['callback_data']);
    expect($duty->refresh()->status)->toBe(DutyStatus::Confirmed)->and(HeartTransaction::query()->where('type', 'reward')->count())->toBe(0);
});

it('offers neutral mood as the middle Telegram answer and records its immediate selection once', function (): void {
    $pair = AgreementScenario::create();
    $account = TelegramAccount::factory()->create(['user_id' => $pair->first->id]);
    $prompt = app(RequestPartnerMood::class)->execute($pair->second, $pair->couple);
    $delivery = NotificationDelivery::query()->where('kind', NotificationKind::MoodPrompt)->sole();
    $message = app(DeliveryLifecycle::class)->claim($delivery);
    expect($message)->not->toBeNull();
    expect($message->keyboard['inline_keyboard'])->toHaveCount(1);
    $buttons = $message->keyboard['inline_keyboard'][0];
    expect(array_column($buttons, 'text'))->toBe(['😞', '🙁', '😐', '🙂', '😄']);
    $reply = callbackResponse($this, 910, $account, $buttons[2]['callback_data'])->json();
    expect(MoodEntry::query()->count())->toBe(1);
    emojiResponse($this, 911, $account, '/skip')->assertJsonFragment(['text' => 'Настроение сохранено без эмоджи.']);
    emojiResponse($this, 912, $account, '😊')->assertJsonMissingPath('reply_markup');
    expect(MoodEntry::query()->sole()->emoji)->toBeNull();
    expect(MoodEntry::query()->sole()->level)->toBe(MoodLevel::Neutral)->and(MoodEntry::query()->sole()->source)->toBe(MoodSource::Telegram)->and($prompt->refresh()->answered_at)->not->toBeNull();
});

it('rejects foreign stale expired and ended-pair emoji replies', function (string $case): void {
    $pair = AgreementScenario::create();
    $account = TelegramAccount::factory()->create(['user_id' => $pair->first->id]);
    $entry = app(RecordMood::class)->execute($pair->first, $pair->couple, new MoodData(MoodLevel::Good, Str::uuid()->toString()));
    app(KeyboardFactory::class)->createMoodEmoji($entry, $account);
    if ($case === 'foreign') {
        $account = TelegramAccount::factory()->create(['user_id' => $pair->second->id]);
    }
    if ($case === 'stale') {
        app(RecordMood::class)->execute($pair->first, $pair->couple, new MoodData(MoodLevel::Neutral, Str::uuid()->toString()));
    }
    if ($case === 'expired') {
        $this->travelTo(TelegramCallbackAction::query()->firstOrFail()->expires_at);
    }
    if ($case === 'ended') {
        app(EndCouple::class)->execute($pair->first, $pair->couple);
    }
    emojiResponse($this, 920, $account, '😊')->assertJsonMissingPath('reply_markup');
    expect($entry->refresh()->emoji)->toBeNull()->and(NotificationDelivery::query()->where('kind', NotificationKind::MoodEmoji)->count())->toBe(0);
})->with(['foreign', 'stale', 'expired', 'ended']);
