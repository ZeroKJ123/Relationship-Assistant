<?php

declare(strict_types=1);

use App\Actions\Auth\Telegram\ConsumeTelegramLinkCode;
use App\Actions\Auth\Telegram\IssueTelegramLinkCode;
use App\Actions\Reminders\CreateReminder;
use App\Data\Reminders\ReminderData;
use App\Data\Telegram\IncomingUpdate;
use App\Enums\ReminderStatus;
use App\Models\TelegramAccount;
use App\Models\TelegramLinkCode;
use App\Models\User;
use App\Services\Telegram\WebhookHandler;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\AgreementScenario;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    config(['services.telegram.enabled' => true, 'services.telegram.bot_token' => '123456789:fake-test-token', 'services.telegram.mode' => 'polling', 'services.telegram.bot_username' => 'HomeAssistTestBot']);
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('issues a UUID for a private start and links only the authenticated profile once', function (): void {
    $reply = app(IssueTelegramLinkCode::class)->execute(new IncomingUpdate(1, '123456', 'Анна', '/start', null, null));
    $code = explode("\n\n", $reply['text'])[1];
    expect(Str::isUuid($code))->toBeTrue();
    expect($reply['reply_markup']['inline_keyboard'][0][0])->toBe(['text' => $code, 'copy_text' => ['text' => $code]]);
    $record = TelegramLinkCode::query()->sole();
    expect($record->code_hash)->toBe(hash('sha256', $code));
    expect($record->getRawOriginal('identity'))->not->toContain('123456');
    expect($record->toArray())->not->toHaveKeys(['identity', 'code_hash', 'identity_hash']);
    $user = User::factory()->create();
    $this->asUser($user)->post(route('profile.telegram.link'), ['telegram_code' => strtoupper($code)])->assertRedirect(route('profile.show'));
    expect($user->telegramAccount()->sole()->chat_id)->toBe('123456');
    expect($record->refresh()->identity)->toBeNull()->and($record->consumed_at)->not->toBeNull();
    $this->post(route('profile.telegram.link'), ['telegram_code' => $code])->assertSessionHasErrors('telegram_code')->assertSessionMissing('_old_input.telegram_code');
    expect(TelegramAccount::query()->count())->toBe(1);
});

it('invalidates the old code on a new start but preserves a duplicate update reply', function (): void {
    $update = new IncomingUpdate(10, '123456', '', '/start', null, null);
    $handler = app(WebhookHandler::class);
    $reply = $handler->execute($update, hash('sha256', 'start-10'));
    expect($handler->execute($update, hash('sha256', 'start-10')))->toBe($reply);
    $old = explode("\n\n", $reply['text'])[1];
    $new = app(IssueTelegramLinkCode::class)->execute(new IncomingUpdate(11, '123456', '', '/start', null, null));
    expect(explode("\n\n", $new['text'])[1])->not->toBe($old);
    expect(TelegramLinkCode::query()->count())->toBe(1);
    expect(fn () => app(ConsumeTelegramLinkCode::class)->execute(User::factory()->create(), $old))->toThrow(ValidationException::class);
});

it('rejects an expired code including its exact expiry boundary', function (): void {
    $code = Str::uuid()->toString();
    $record = TelegramLinkCode::factory()->create(['code_hash' => hash('sha256', $code)]);
    CarbonImmutable::setTestNow($record->expires_at);
    $this->asUser(User::factory()->create())->post(route('profile.telegram.link'), ['telegram_code' => $code])->assertSessionHasErrors('telegram_code');
    expect(TelegramAccount::query()->count())->toBe(0);
});

it('refuses reassignment of either a Telegram identity or an already linked application user', function (string $case): void {
    $code = Str::uuid()->toString();
    $record = TelegramLinkCode::factory()->create(['code_hash' => hash('sha256', $code)]);
    $user = User::factory()->create();
    $account = TelegramAccount::factory()->create($case === 'identity' ? ['telegram_id' => $record->identity['telegram_id']] : ['user_id' => $user->id]);
    $this->asUser($user)->post(route('profile.telegram.link'), ['telegram_code' => $code])->assertSessionHasErrors('telegram_code');
    expect(TelegramAccount::query()->sole()->id)->toBe($account->id);
    expect($record->refresh()->consumed_at)->toBeNull();
})->with(['identity', 'user']);

it('requires login valid UUID and enabled transport and keeps the code out of flashed input', function (): void {
    $code = Str::uuid()->toString();
    $this->post(route('profile.telegram.link'), ['telegram_code' => $code])->assertRedirect(route('login'));
    $this->asUser(User::factory()->create())->post(route('profile.telegram.link'), ['telegram_code' => 'invalid'])->assertSessionHasErrors('telegram_code')->assertSessionMissing('_old_input.telegram_code');
    config(['services.telegram.enabled' => false]);
    $this->post(route('profile.telegram.link'), ['telegram_code' => $code])->assertServiceUnavailable();
    expect(TelegramAccount::query()->count())->toBe(0);
});

it('shows the code form in a private profile without requiring a webhook secret', function (): void {
    $this->withoutVite()->asUser(User::factory()->create())->get(route('profile.show'))->assertOk()->assertSee('Код из Telegram')->assertSee('Связать Telegram')->assertSee('https://t.me/HomeAssistTestBot')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
});

it('never issues another code for a linked chat and rolls back a claimed code with its enclosing transaction', function (): void {
    $code = Str::uuid()->toString();
    $record = TelegramLinkCode::factory()->create(['code_hash' => hash('sha256', $code)]);
    $user = User::factory()->create();
    expect(fn () => DB::transaction(function () use ($user, $code): void {
        app(ConsumeTelegramLinkCode::class)->execute($user, $code);
        throw new RuntimeException('Rollback');
    }))->toThrow(RuntimeException::class);
    expect($record->refresh()->consumed_at)->toBeNull();
    expect(TelegramAccount::query()->count())->toBe(0);
    app(ConsumeTelegramLinkCode::class)->execute($user, $code);
    $reply = app(IssueTelegramLinkCode::class)->execute(new IncomingUpdate(12, $user->telegramAccount()->sole()->telegram_id, '', '/start', null, null));
    expect($reply['text'])->toContain('уже связан');
    expect(TelegramLinkCode::query()->count())->toBe(1);
});

it('delivers a reminder through the queue to the chat linked by its start UUID', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 04:00:00', 'UTC'));
    config(['queue.default' => 'database']);
    $pair = AgreementScenario::create();
    $reply = app(IssueTelegramLinkCode::class)->execute(new IncomingUpdate(20, '123456', '', '/start', null, null));
    app(ConsumeTelegramLinkCode::class)->execute($pair->second, explode("\n\n", $reply['text'])[1]);
    $reminder = app(CreateReminder::class)->execute($pair->first, $pair->couple, new ReminderData('Проверка связи', now()->toImmutable()->addMinute(), Str::uuid()->toString()));
    CarbonImmutable::setTestNow(now()->addMinute());
    Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 23]])]);
    $this->artisan('care:process-due')->assertSuccessful();
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'telegram,default', '--once' => true])->assertSuccessful();
    expect($reminder->refresh()->status)->toBe(ReminderStatus::Delivered);
    Http::assertSent(fn (Request $request): bool => $request['chat_id'] === '123456' && str_contains($request['text'], 'Проверка связи'));
    Http::assertSentCount(1);
});
