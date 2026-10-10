<?php

declare(strict_types=1);

use App\Enums\DeliveryStatus;
use App\Models\TelegramLinkCode;
use App\Models\TelegramUpdateReceipt;
use App\Services\Telegram\BotClient;
use App\Services\Telegram\PollTelegramUpdates;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(DatabaseMigrations::class);

it('sends a free emoji reply prompt through the Telegram transport', function (): void {
    Http::fake(['*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 22]])]);
    $markup = ['force_reply' => true, 'input_field_placeholder' => 'Ваш эмоджи'];
    expect(app(BotClient::class)->sendMessage('123456', 'Напишите эмоджи', $markup)->status)->toBe(DeliveryStatus::Sent);
    Http::assertSent(fn (Request $request): bool => $request['reply_markup'] === $markup && $request['chat_id'] === '123456');
});

beforeEach(function (): void {
    config(['services.telegram.enabled' => true, 'services.telegram.bot_token' => '123456789:fake-test-token', 'services.telegram.mode' => 'polling']);
    $this->payload = ['update_id' => 12, 'message' => ['from' => ['id' => 123456, 'is_bot' => false], 'chat' => ['id' => 123456, 'type' => 'private'], 'text' => '/start']];
    $this->cursorKey = 'telegram-poll:'.hash('sha256', config('services.telegram.bot_token'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('polls sends the UUID after commit and persists the next offset across batches', function (): void {
    Http::fake([
        '*/getWebhookInfo' => Http::response(['ok' => true, 'result' => ['url' => '']]),
        '*/getUpdates' => Http::sequence()->push(['ok' => true, 'result' => [$this->payload]])->push(['ok' => true, 'result' => []]),
        '*/sendMessage' => function (Request $request) {
            expect(DB::transactionLevel())->toBe(0);
            expect(TelegramUpdateReceipt::query()->count())->toBe(1);
            expect($request['chat_id'])->toBe('123456');
            expect($request['text'])->toContain('Ваш код');
            expect($request['protect_content'])->toBeFalse();
            $button = $request['reply_markup']['inline_keyboard'][0][0];
            expect($button['copy_text']['text'])->toBe($button['text']);
            expect(hash('sha256', $button['copy_text']['text']))->toBe(TelegramLinkCode::query()->sole()->code_hash);

            return Http::response(['ok' => true, 'result' => ['message_id' => 22]]);
        },
    ]);
    $this->artisan('telegram:poll-updates', ['--once' => true])->assertSuccessful();
    expect(Cache::get($this->cursorKey))->toBe(13);
    $this->artisan('telegram:poll-updates', ['--once' => true])->assertSuccessful();
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/getUpdates') && $request['offset'] === 13);
    expect(TelegramLinkCode::query()->count())->toBe(1);
    Http::assertSentCount(5);
});

it('retries an uncertain send with the same code and advances only after the reply succeeds', function (): void {
    Http::fake([
        '*/getUpdates' => Http::response(['ok' => true, 'result' => [$this->payload]]),
        '*/sendMessage' => Http::sequence()->pushStatus(Response::HTTP_INTERNAL_SERVER_ERROR)->push(['ok' => true, 'result' => ['message_id' => 22]]),
    ]);
    $poll = app(PollTelegramUpdates::class);
    expect($poll->execute())->toBeFalse();
    expect(Cache::get($this->cursorKey))->toBeNull();
    $hash = TelegramLinkCode::query()->sole()->code_hash;
    CarbonImmutable::setTestNow(now()->addSeconds(6));
    expect($poll->execute())->toBeTrue();
    expect(Cache::get($this->cursorKey))->toBe(13);
    expect(TelegramLinkCode::query()->sole()->code_hash)->toBe($hash);
    Http::assertSentCount(4);
});

it('honours reply rate limits and does not stall later updates for a blocked chat', function (): void {
    Http::fake([
        '*/getUpdates' => Http::response(['ok' => true, 'result' => [$this->payload]]),
        '*/sendMessage' => Http::sequence()->push(['ok' => false, 'error_code' => Response::HTTP_TOO_MANY_REQUESTS, 'parameters' => ['retry_after' => 30]], Response::HTTP_TOO_MANY_REQUESTS)->push(['ok' => false, 'error_code' => Response::HTTP_FORBIDDEN], Response::HTTP_FORBIDDEN),
    ]);
    $poll = app(PollTelegramUpdates::class);
    expect($poll->execute())->toBeFalse();
    expect($poll->execute())->toBeTrue();
    Http::assertSentCount(2);
    CarbonImmutable::setTestNow(now()->addSeconds(31));
    expect($poll->execute())->toBeTrue();
    expect(Cache::get($this->cursorKey))->toBe(13);
});

it('ignores group messages and foreign sender identities without issuing or sending a code', function (string $case): void {
    if ($case === 'group') {
        $this->payload['message']['chat']['type'] = 'group';
    }
    if ($case === 'foreign') {
        $this->payload['message']['from']['id'] = 654321;
    }
    Http::fake(['*/getUpdates' => Http::response(['ok' => true, 'result' => [$this->payload]])]);
    expect(app(PollTelegramUpdates::class)->execute())->toBeTrue();
    expect(Cache::get($this->cursorKey))->toBe(13);
    expect(TelegramLinkCode::query()->count())->toBe(0);
    Http::assertSentCount(1);
})->with(['group', 'foreign']);

it('does not poll when disabled or when another receiver holds the lock', function (): void {
    $lock = Cache::lock($this->cursorKey.':lock', 120);
    expect($lock->get())->toBeTrue();
    expect(app(PollTelegramUpdates::class)->execute())->toBeFalse();
    $lock->release();
    config(['services.telegram.enabled' => false]);
    expect(app(PollTelegramUpdates::class)->execute())->toBeFalse();
    Http::assertNothingSent();
});

it('refuses an active webhook and does not remove it or discard pending updates', function (): void {
    Http::fake(['*/getWebhookInfo' => Http::response(['ok' => true, 'result' => ['url' => 'https://example.test/webhook']])]);
    $this->artisan('telegram:poll-updates', ['--once' => true])->assertFailed();
    Http::assertSentCount(1);
});

it('retains the cursor on polling failure without leaking network exception details', function (): void {
    Http::fake(fn (): never => throw new ConnectionException('fake-token-secret'));
    expect(app(PollTelegramUpdates::class)->execute())->toBeFalse();
    expect(Cache::get($this->cursorKey))->toBeNull();
    expect(TelegramLinkCode::query()->count())->toBe(0);
});
