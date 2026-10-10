<?php

declare(strict_types=1);

use App\Actions\Auth\Telegram\ConfirmTelegramIdentity;
use App\Actions\Auth\Telegram\FinishTelegramLogin;
use App\Actions\Auth\Telegram\StartTelegramLogin;
use App\Data\Telegram\IncomingUpdate;
use App\Enums\TelegramLoginStatus;
use App\Models\TelegramAccount;
use App\Models\TelegramLoginAttempt;
use App\Models\TelegramUpdateReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['services.telegram.enabled' => true, 'services.telegram.bot_token' => '123456789:fake-test-token', 'services.telegram.bot_username' => 'HomeAssistTestBot', 'services.telegram.webhook_secret' => str_repeat('test-secret-', 4)]);
    Http::fake(['*/answerCallbackQuery' => Http::response(['ok' => true, 'result' => true])]);
});

/** @return array{attempt: TelegramLoginAttempt, browser_token: string, start_token: string} */
function confirmedTelegramAttempt(?User $user = null, string $identity = '123456'): array
{
    $started = app(StartTelegramLogin::class)->execute($user);
    $reply = app(ConfirmTelegramIdentity::class)->execute(new IncomingUpdate(1, $identity, 'Анна', sprintf('/start %s', $started['start_token']), null, null));
    $callback = $reply['reply_markup']['inline_keyboard'][0][0]['callback_data'];
    app(ConfirmTelegramIdentity::class)->execute(new IncomingUpdate(2, $identity, 'Анна', null, $callback, 'callback-1'));

    return $started;
}

it('registers a Telegram identity only after confirmation and gives a wallet and recovery codes', function (): void {
    $started = confirmedTelegramAttempt();
    $result = app(FinishTelegramLogin::class)->execute($started['attempt']->id, $started['browser_token'], null);
    expect($result['user']->name)->toBe('Анна')->and($result['user']->is_admin)->toBeFalse()->and($result['user']->login)->toBeNull()->and($result['recovery_codes'])->toHaveCount(8);
    $this->assertDatabaseHas('heart_wallets', ['user_id' => $result['user']->id]);
    expect($started['attempt']->refresh()->status)->toBe(TelegramLoginStatus::Consumed)->and($started['attempt']->identity)->toBeNull();
    expect(fn () => app(FinishTelegramLogin::class)->execute($started['attempt']->id, $started['browser_token'], null))->toThrow(ValidationException::class);
    expect(User::query()->count())->toBe(1);
});

it('logs into an existing linked account without duplicating or changing the profile', function (): void {
    $account = TelegramAccount::factory()->create(['telegram_id' => '123456', 'chat_id' => '123456']);
    $started = confirmedTelegramAttempt();
    $result = app(FinishTelegramLogin::class)->execute($started['attempt']->id, $started['browser_token'], null);
    expect($result['user']->id)->toBe($account->user_id)->and($result['recovery_codes'])->toBe([])->and(User::query()->count())->toBe(1);
});

it('links only the authenticated initiating account and refuses identity collisions', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $started = confirmedTelegramAttempt($user);
    expect(fn () => app(FinishTelegramLogin::class)->execute($started['attempt']->id, $started['browser_token'], $other))->toThrow(ValidationException::class);
    app(FinishTelegramLogin::class)->execute($started['attempt']->id, $started['browser_token'], $user);
    $collision = confirmedTelegramAttempt($other);
    expect(fn () => app(FinishTelegramLogin::class)->execute($collision['attempt']->id, $collision['browser_token'], $other))->toThrow(ValidationException::class);
    expect(TelegramAccount::query()->count())->toBe(1);
});

it('refuses replacing an already linked Telegram identity', function (): void {
    $account = TelegramAccount::factory()->create();
    $started = confirmedTelegramAttempt($account->user, '999998');
    expect(fn () => app(FinishTelegramLogin::class)->execute($started['attempt']->id, $started['browser_token'], $account->user))->toThrow(ValidationException::class);
    expect($account->refresh()->telegram_id)->not->toBe('999998');
});

it('rejects foreign browser tokens and the expiry boundary', function (): void {
    $started = confirmedTelegramAttempt();
    expect(fn () => app(FinishTelegramLogin::class)->execute($started['attempt']->id, 'foreign', null))->toThrow(ValidationException::class);
    $this->travelTo($started['attempt']->expires_at);
    expect(fn () => app(FinishTelegramLogin::class)->execute($started['attempt']->id, $started['browser_token'], null))->toThrow(ValidationException::class);
    expect(User::query()->count())->toBe(0);
});

it('requires a started bot and explicit confirmation by the same identity', function (): void {
    $started = app(StartTelegramLogin::class)->execute(null);
    expect(fn () => app(FinishTelegramLogin::class)->execute($started['attempt']->id, $started['browser_token'], null))->toThrow(ValidationException::class);
    $reply = app(ConfirmTelegramIdentity::class)->execute(new IncomingUpdate(1, '123456', '', sprintf('/start %s', $started['start_token']), null, null));
    $callback = $reply['reply_markup']['inline_keyboard'][0][0]['callback_data'];
    expect(fn () => app(ConfirmTelegramIdentity::class)->execute(new IncomingUpdate(2, '999999', 'Другой', null, $callback, 'x')))->toThrow(ValidationException::class);
    expect(fn () => app(ConfirmTelegramIdentity::class)->execute(new IncomingUpdate(3, '999999', '', sprintf('/start %s', $started['start_token']), null, null)))->toThrow(ValidationException::class);
    expect(fn () => app(FinishTelegramLogin::class)->execute($started['attempt']->id, $started['browser_token'], null))->toThrow(ValidationException::class);
});

it('allows cancelling a browser attempt without creating a Telegram account', function (): void {
    $started = confirmedTelegramAttempt();
    app(StartTelegramLogin::class)->cancel($started['attempt']->id, $started['browser_token']);
    expect(fn () => app(FinishTelegramLogin::class)->execute($started['attempt']->id, $started['browser_token'], null))->toThrow(ValidationException::class);
    expect(TelegramAccount::query()->count())->toBe(0);
});

it('requires webhook secret and ignores group and mismatched actor updates', function (): void {
    $payload = ['update_id' => 20, 'message' => ['from' => ['id' => 123456, 'is_bot' => false, 'first_name' => 'Анна'], 'chat' => ['id' => 123456, 'type' => 'private'], 'text' => '/start']];
    $this->postJson(route('telegram.webhook'), $payload)->assertForbidden();
    $secret = ['X-Telegram-Bot-Api-Secret-Token' => config('services.telegram.webhook_secret')];
    $this->postJson(route('telegram.webhook'), $payload, $secret)->assertOk();
    $payload['update_id'] = 21;
    $payload['message']['chat']['type'] = 'group';
    $this->postJson(route('telegram.webhook'), $payload, $secret)->assertOk()->assertJson(['ok' => true]);
    $payload['message']['chat']['type'] = 'private';
    $payload['message']['chat']['id'] = 999999;
    $this->postJson(route('telegram.webhook'), $payload, $secret)->assertOk()->assertJson(['ok' => true]);
    expect(TelegramUpdateReceipt::query()->count())->toBe(1);
});

it('keeps browser completion behind its own session and regenerates the authenticated session', function (): void {
    $this->post(route('telegram.login.store'))->assertRedirect();
    $started = session('telegram_login');
    $attempt = TelegramLoginAttempt::query()->findOrFail($started['attempt_id']);
    $this->get(route('telegram.login.show', $attempt))->assertOk()->assertSee($attempt->verification_code);
    $reply = app(ConfirmTelegramIdentity::class)->execute(new IncomingUpdate(1, '123456', 'Анна', sprintf('/start %s', $started['start_token']), null, null));
    app(ConfirmTelegramIdentity::class)->execute(new IncomingUpdate(2, '123456', 'Анна', null, $reply['reply_markup']['inline_keyboard'][0][0]['callback_data'], 'x'));
    $this->withSession(['telegram_login' => []])->patch(route('telegram.login.update', $attempt))->assertForbidden();
    Http::fake(['*/getUserProfilePhotos' => Http::response(['ok' => true, 'result' => ['photos' => []]])]);
    $this->withSession(['telegram_login' => $started])->patch(route('telegram.login.update', $attempt))->assertRedirect(route('profile.show'));
    $this->assertAuthenticated();
    $this->get(route('profile.show'))->assertOk()->assertSee('Telegram привязан');
    expect(session()->has('telegram_login'))->toBeFalse();
});

it('does not show or start Telegram login while configuration is unavailable', function (): void {
    config(['services.telegram.enabled' => false]);
    $this->get(route('login'))->assertOk()->assertDontSee('Войти через Telegram');
    $this->post(route('telegram.login.store'))->assertSessionHasErrors('telegram');
    expect(TelegramLoginAttempt::query()->count())->toBe(0);
});

it('deduplicates webhook start and stores sensitive replies encrypted', function (): void {
    $started = app(StartTelegramLogin::class)->execute(null);
    $payload = ['update_id' => 30, 'message' => ['from' => ['id' => 123456, 'is_bot' => false, 'first_name' => 'Анна'], 'chat' => ['id' => 123456, 'type' => 'private'], 'text' => sprintf('/start %s', $started['start_token'])]];
    $secret = ['X-Telegram-Bot-Api-Secret-Token' => config('services.telegram.webhook_secret')];
    $first = $this->postJson(route('telegram.webhook'), $payload, $secret)->assertOk()->json();
    $this->postJson(route('telegram.webhook'), $payload, $secret)->assertExactJson($first);
    expect(TelegramUpdateReceipt::query()->count())->toBe(1);
    $stored = DB::table('telegram_update_receipts')->value('reply');
    expect($stored)->not->toContain('callback_data');
    $payload['message']['from']['id'] = 999999;
    $payload['message']['chat']['id'] = 999999;
    $this->postJson(route('telegram.webhook'), $payload, $secret)->assertExactJson(['ok' => true]);
});
