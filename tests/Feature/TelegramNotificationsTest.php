<?php

declare(strict_types=1);

use App\Actions\Couples\EndCouple;
use App\Actions\Mood\CreateMoodPrompt;
use App\Actions\Mood\RecordMood;
use App\Actions\Mood\SetMoodSchedule;
use App\Actions\Notifications\SendTelegramNotification;
use App\Actions\Reminders\CancelReminder;
use App\Actions\Reminders\CreateReminder;
use App\Actions\Reminders\PrepareReminder;
use App\Actions\Requests\CreatePartnerRequest;
use App\Actions\Requests\RespondToRequest;
use App\Data\Mood\MoodData;
use App\Data\Reminders\ReminderData;
use App\Data\Requests\NewRequestData;
use App\Data\Requests\RequestResponseData;
use App\Data\Telegram\SendResult;
use App\Enums\DeliveryStatus;
use App\Enums\MoodLevel;
use App\Enums\NotificationKind;
use App\Enums\ReminderStatus;
use App\Enums\RequestResponse;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Jobs\SendTelegramNotificationJob;
use App\Models\MoodEntry;
use App\Models\NotificationDelivery;
use App\Models\TelegramAccount;
use App\Services\Notifications\DeliveryLifecycle;
use Carbon\CarbonImmutable;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\AgreementScenario;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 06:30:00', 'UTC'));
    config(['services.telegram.enabled' => true, 'services.telegram.bot_token' => '123456789:fake-test-token', 'queue.default' => 'database']);
    Queue::fake();
    $this->pair = AgreementScenario::create();
    $this->account = TelegramAccount::factory()->create(['user_id' => $this->pair->second->id]);
    $this->reminder = app(CreateReminder::class)->execute($this->pair->first, $this->pair->couple, new ReminderData('Личное напоминание', CarbonImmutable::now()->addMinute(), Str::uuid()->toString()));
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinute());
    app(PrepareReminder::class)->execute($this->reminder, 0);
    $this->delivery = NotificationDelivery::query()->sole();
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('sends only to the bound partner outside transactions and persists an idempotent result', function (): void {
    Http::fake(function (Request $request): PromiseInterface {
        expect(DB::transactionLevel())->toBe(0);
        expect($request['chat_id'])->toBe($this->account->chat_id);
        expect($request['text'])->toContain('Личное напоминание');
        expect($request['protect_content'])->toBeTrue();

        return Http::response(['ok' => true, 'result' => ['message_id' => 42]]);
    });
    app(SendTelegramNotification::class)->execute($this->delivery->id);
    app(SendTelegramNotification::class)->execute($this->delivery->id);
    expect($this->delivery->refresh()->status)->toBe(DeliveryStatus::Sent);
    expect($this->delivery->message_id)->toBe('42');
    expect($this->delivery->attempts)->toBe(1);
    expect($this->reminder->refresh()->status)->toBe(ReminderStatus::Delivered);
    Http::assertSentCount(1);
    Queue::assertPushed(SendTelegramNotificationJob::class, 1);
});

it('rolls back the notification together with the domain event and dispatches nothing before commit', function (): void {
    $count = NotificationDelivery::query()->count();
    Queue::fake();
    expect(fn () => DB::transaction(function (): void {
        app(RecordMood::class)->execute($this->pair->first, $this->pair->couple, new MoodData(MoodLevel::Good, Str::uuid()->toString()));
        Queue::assertNothingPushed();
        throw new RuntimeException('Rollback test');
    }))->toThrow(RuntimeException::class);
    expect(MoodEntry::query()->count())->toBe(0);
    expect(NotificationDelivery::query()->count())->toBe($count);
    Queue::assertNothingPushed();
});

it('honours Telegram retry_after without repeating preparation or sending before the retry time', function (): void {
    Http::fakeSequence()->push(['ok' => false, 'error_code' => Response::HTTP_TOO_MANY_REQUESTS, 'parameters' => ['retry_after' => 1200]], Response::HTTP_TOO_MANY_REQUESTS)->push(['ok' => true, 'result' => ['message_id' => 43]]);
    app(SendTelegramNotification::class)->execute($this->delivery->id);
    expect($this->delivery->refresh()->status)->toBe(DeliveryStatus::Pending);
    expect($this->delivery->available_at->equalTo(CarbonImmutable::now()->addSeconds(1200)))->toBeTrue();
    expect($this->reminder->refresh()->status)->toBe(ReminderStatus::Sending);
    expect(fn () => app(CancelReminder::class)->execute($this->pair->first, $this->reminder, $this->reminder->version))->toThrow(ValidationException::class);
    app(SendTelegramNotification::class)->execute($this->delivery->id);
    Http::assertSentCount(1);
    CarbonImmutable::setTestNow($this->delivery->available_at);
    app(SendTelegramNotification::class)->execute($this->delivery->id);
    expect($this->reminder->refresh()->status)->toBe(ReminderStatus::Delivered);
    expect($this->delivery->refresh()->attempts)->toBe(2);
    Http::assertSentCount(2);
});

it('records blocked chat without undoing an accepted request', function (): void {
    $request = app(CreatePartnerRequest::class)->execute($this->pair->first, $this->pair->couple, new NewRequestData(RequestType::Service, 'Помощь', CarbonImmutable::tomorrow()));
    app(RespondToRequest::class)->execute($this->pair->second, $this->pair->couple, $request, new RequestResponseData(RequestResponse::Agree, 0));
    Http::fake(['*' => Http::response(['ok' => false, 'error_code' => Response::HTTP_FORBIDDEN], Response::HTTP_FORBIDDEN)]);
    app(SendTelegramNotification::class)->execute($this->delivery->id);
    expect($this->delivery->refresh()->failure_reason)->toBe('blocked');
    expect($this->delivery->status)->toBe(DeliveryStatus::Failed);
    expect($this->reminder->refresh()->status)->toBe(ReminderStatus::Failed);
    expect($request->refresh()->status)->toBe(RequestStatus::Accepted);
});

it('sanitizes connection failures and retries a bounded number of times', function (): void {
    Log::spy();
    Http::fake(fn (): never => throw new ConnectionException('private URL and 123456789:fake-test-token'));
    for ($attempt = 0; $attempt < NotificationDelivery::MAXIMUM_ATTEMPTS; $attempt++) {
        CarbonImmutable::setTestNow($this->delivery->refresh()->available_at);
        app(SendTelegramNotification::class)->execute($this->delivery->id);
    }
    expect($this->delivery->refresh()->status)->toBe(DeliveryStatus::Failed);
    expect($this->delivery->failure_reason)->toBe('attempts_exhausted');
    expect($this->delivery->attempts)->toBe(NotificationDelivery::MAXIMUM_ATTEMPTS);
    Log::shouldHaveReceived('warning')->with('telegram.transport_failed', ['reason' => 'connection_uncertain'])->times(NotificationDelivery::MAXIMUM_ATTEMPTS);
    Log::shouldNotHaveReceived('error', ['private URL and 123456789:fake-test-token']);
});

it('does not send cancelled reminders or messages from ended couples', function (string $case): void {
    Http::fake();
    if ($case === 'cancelled') {
        app(CancelReminder::class)->execute($this->pair->first, $this->reminder->refresh(), 1);
    }
    if ($case === 'ended') {
        app(EndCouple::class)->execute($this->pair->first, $this->pair->couple);
    }
    app(SendTelegramNotification::class)->execute($this->delivery->id);
    expect($this->delivery->refresh()->status)->toBe(DeliveryStatus::Cancelled);
    expect($this->reminder->refresh()->status)->toBe(ReminderStatus::Cancelled);
    Http::assertNothingSent();
})->with(['cancelled', 'ended']);

it('expires late reminders without contacting Telegram', function (): void {
    Http::fake();
    CarbonImmutable::setTestNow($this->reminder->due_at->addHours(25));
    app(SendTelegramNotification::class)->execute($this->delivery->id);
    expect($this->delivery->refresh()->failure_reason)->toBe('expired');
    expect($this->reminder->refresh()->status)->toBe(ReminderStatus::Expired);
    Http::assertNothingSent();
});

it('refuses a changed Telegram identity or a group chat', function (string $case): void {
    Http::fake();
    $this->account->forceFill($case === 'group' ? ['chat_id' => '-100123'] : ['telegram_id' => '987654321123', 'chat_id' => '987654321123'])->save();
    app(SendTelegramNotification::class)->execute($this->delivery->id);
    expect($this->delivery->refresh()->failure_reason)->toBe('account_changed');
    Http::assertNothingSent();
})->with(['identity', 'group']);

it('waits for a missing Telegram account without consuming transport attempts', function (): void {
    Http::fake();
    $this->account->delete();
    app(SendTelegramNotification::class)->execute($this->delivery->id);
    expect($this->delivery->refresh()->status)->toBe(DeliveryStatus::Pending);
    expect($this->delivery->attempts)->toBe(0);
    expect($this->delivery->failure_reason)->toBe('account_unavailable');
    Http::assertNothingSent();
});

it('recovers a lost lease while rejecting the old worker result', function (): void {
    $lifecycle = app(DeliveryLifecycle::class);
    $first = $lifecycle->claim($this->delivery);
    expect($lifecycle->claim($this->delivery->refresh()))->toBeNull();
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addSeconds(NotificationDelivery::LEASE_SECONDS));
    $second = $lifecycle->claim($this->delivery->refresh());
    expect($second->leaseToken)->not->toBe($first->leaseToken);
    $lifecycle->complete($this->delivery, $first->leaseToken, new SendResult(DeliveryStatus::Sent, 'sent', '1'));
    expect($this->delivery->refresh()->status)->toBe(DeliveryStatus::Processing);
    $lifecycle->complete($this->delivery, $second->leaseToken, new SendResult(DeliveryStatus::Sent, 'sent', '2'));
    expect($this->delivery->refresh()->message_id)->toBe('2');
    expect($this->reminder->refresh()->status)->toBe(ReminderStatus::Delivered);
});

it('recovers missed dispatch and keeps disabled or synchronous transport from sending', function (): void {
    $this->delivery->forceFill(['queued_until' => null])->save();
    Queue::fake();
    $this->artisan('telegram:dispatch-notifications')->assertSuccessful();
    Queue::assertPushed(SendTelegramNotificationJob::class, fn (SendTelegramNotificationJob $job): bool => $job->deliveryId === $this->delivery->id && $job->queue === 'telegram');
    $this->artisan('telegram:dispatch-notifications')->assertSuccessful();
    Queue::assertPushed(SendTelegramNotificationJob::class, 1);
    Http::fake();
    config(['services.telegram.enabled' => false]);
    app(SendTelegramNotification::class)->execute($this->delivery->id);
    expect($this->delivery->refresh()->attempts)->toBe(0);
    Http::assertNothingSent();
    Queue::fake();
    config(['services.telegram.enabled' => true, 'queue.default' => 'sync']);
    $this->artisan('telegram:dispatch-notifications')->assertSuccessful();
    Queue::assertNothingPushed();
});

it('notifies each request participant about the other participant event once', function (): void {
    $request = app(CreatePartnerRequest::class)->execute($this->pair->first, $this->pair->couple, new NewRequestData(RequestType::Service, 'Помощь', CarbonImmutable::tomorrow(), operationKey: Str::uuid()->toString()));
    app(RespondToRequest::class)->execute($this->pair->second, $this->pair->couple, $request, new RequestResponseData(RequestResponse::Agree, 0));
    app(RespondToRequest::class)->execute($this->pair->second, $this->pair->couple, $request, new RequestResponseData(RequestResponse::Agree, 0));
    $events = NotificationDelivery::query()->where('kind', NotificationKind::RequestEvent)->orderBy('id')->get();
    expect($events)->toHaveCount(2);
    expect($events->pluck('recipient_id')->all())->toBe([$this->pair->second->id, $this->pair->first->id]);
});

it('does not lose a committed domain event when enqueuing fails', function (): void {
    Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue unavailable'));
    Log::spy();
    $request = app(CreatePartnerRequest::class)->execute($this->pair->first, $this->pair->couple, new NewRequestData(RequestType::Service, 'Помощь', CarbonImmutable::tomorrow()));
    expect($request->exists)->toBeTrue();
    $delivery = NotificationDelivery::query()->where('kind', NotificationKind::RequestEvent)->sole();
    expect($delivery->status)->toBe(DeliveryStatus::Pending);
    expect($delivery->queued_until)->toBeNull();
    Log::shouldHaveReceived('error')->with('notification.enqueue_failed', ['delivery_id' => $delivery->id, 'reason' => 'queue_unavailable', 'exception_type' => RuntimeException::class])->once();
});

it('cancels disabled answered or expired mood prompts before contacting Telegram', function (string $case): void {
    $this->pair->couple->members()->update(['joined_at' => CarbonImmutable::now()->subDay()]);
    TelegramAccount::factory()->create(['user_id' => $this->pair->first->id]);
    $prompt = app(CreateMoodPrompt::class)->execute($this->pair->first, $this->pair->couple, CarbonImmutable::parse('2026-10-11 06:00', 'UTC'));
    $delivery = NotificationDelivery::query()->where('kind', NotificationKind::MoodPrompt)->sole();
    if ($case === 'disabled') {
        app(SetMoodSchedule::class)->execute($this->pair->first, $this->pair->couple, false, 0);
    }
    if ($case === 'answered') {
        app(RecordMood::class)->execute($this->pair->first, $this->pair->couple, new MoodData(MoodLevel::Good, Str::uuid()->toString(), promptId: $prompt->id));
    }
    if ($case === 'expired') {
        CarbonImmutable::setTestNow($prompt->expires_at);
    }
    Http::fake();
    app(SendTelegramNotification::class)->execute($delivery->id);
    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Cancelled);
    Http::assertNothingSent();
})->with(['disabled', 'answered', 'expired']);

it('retries server errors and malformed success responses without claiming delivery', function (array $body, int $status): void {
    Http::fake(['*' => Http::response($body, $status)]);
    app(SendTelegramNotification::class)->execute($this->delivery->id);
    expect($this->delivery->refresh()->status)->toBe(DeliveryStatus::Pending);
    expect($this->delivery->message_id)->toBeNull();
    expect($this->delivery->failure_reason)->toBe('api_unavailable');
})->with([
    [['ok' => false], Response::HTTP_SERVICE_UNAVAILABLE],
    [['ok' => true, 'result' => []], Response::HTTP_OK],
]);

it('keeps private notification text out of the outbox and queued job data', function (): void {
    expect($this->delivery->toJson())->not->toContain('Личное напоминание', $this->account->chat_id);
    Queue::assertPushed(SendTelegramNotificationJob::class, fn (SendTelegramNotificationJob $job): bool => ! str_contains(serialize($job), 'Личное напоминание') && ! str_contains(serialize($job), 'fake-test-token'));
});
