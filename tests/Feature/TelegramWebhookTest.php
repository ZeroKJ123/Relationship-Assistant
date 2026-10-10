<?php

declare(strict_types=1);

use App\Actions\Requests\CreatePartnerRequest;
use App\Data\Requests\NewRequestData;
use App\Data\Telegram\IncomingUpdate;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Enums\TelegramActionStage;
use App\Enums\TelegramOperation;
use App\Models\TelegramAccount;
use App\Models\TelegramCallbackAction;
use App\Models\TelegramLoginAttempt;
use App\Models\TelegramUpdateReceipt;
use App\Services\Telegram\WebhookHandler;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Support\AgreementScenario;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    config(['services.telegram.enabled' => true, 'services.telegram.bot_token' => '123456789:fake-test-token', 'services.telegram.bot_username' => 'HomeAssistTestBot', 'services.telegram.webhook_secret' => str_repeat('test-secret-', 4)]);
});

it('acknowledges callback queries after commit and preserves the domain result when acknowledgement fails', function (): void {
    $pair = AgreementScenario::create();
    $account = TelegramAccount::factory()->create(['user_id' => $pair->second->id]);
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Service, 'Помощь', CarbonImmutable::tomorrow()));
    $token = str_repeat('x', 40);
    TelegramCallbackAction::factory()->create(['telegram_account_id' => $account->id, 'couple_id' => $pair->couple->id, 'source_id' => $request->id, 'source_version' => 0, 'token_hash' => hash('sha256', $token), 'stage' => TelegramActionStage::Confirm, 'operation' => TelegramOperation::Agree]);
    Log::spy();
    Http::fake(function (Request $httpRequest): never {
        expect(DB::transactionLevel())->toBe(0);
        expect($httpRequest->url())->toEndWith('/answerCallbackQuery');
        expect(TelegramUpdateReceipt::query()->count())->toBe(1);
        throw new ConnectionException('secret URL containing fake-test-token');
    });
    $payload = ['update_id' => 900, 'callback_query' => ['id' => 'callback-900', 'from' => ['id' => (int) $account->telegram_id, 'is_bot' => false], 'message' => ['chat' => ['id' => (int) $account->telegram_id, 'type' => 'private']], 'data' => sprintf('act:%s', $token)]];
    $this->postJson(route('telegram.webhook'), $payload, ['X-Telegram-Bot-Api-Secret-Token' => config('services.telegram.webhook_secret')])->assertOk()->assertJsonFragment(['text' => 'Готово. Результат сохранён в приложении.']);
    expect($request->refresh()->status)->toBe(RequestStatus::Accepted)->and($request->duties()->count())->toBe(1);
    Log::shouldHaveReceived('warning')->with('telegram.control_failed', ['method' => 'answerCallbackQuery', 'reason' => 'connection_uncertain'])->once();
});

it('rolls back update receipt and domain action together if the enclosing transaction fails', function (): void {
    $pair = AgreementScenario::create();
    $account = TelegramAccount::factory()->create(['user_id' => $pair->second->id]);
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Service, 'Помощь', CarbonImmutable::tomorrow()));
    $token = str_repeat('y', 40);
    $action = TelegramCallbackAction::factory()->create(['telegram_account_id' => $account->id, 'couple_id' => $pair->couple->id, 'source_id' => $request->id, 'source_version' => 0, 'token_hash' => hash('sha256', $token), 'stage' => TelegramActionStage::Confirm]);
    $update = new IncomingUpdate(901, $account->telegram_id, '', null, sprintf('act:%s', $token), 'callback-901');
    expect(fn () => DB::transaction(function () use ($update): void {
        app(WebhookHandler::class)->execute($update, hash('sha256', '901'));
        throw new RuntimeException('Simulated rollback');
    }))->toThrow(RuntimeException::class);
    expect($request->refresh()->status)->toBe(RequestStatus::Pending)->and($action->refresh()->stage)->toBe(TelegramActionStage::Confirm)->and(TelegramUpdateReceipt::query()->count())->toBe(0);
    app(WebhookHandler::class)->execute($update, hash('sha256', '901'));
    expect($request->refresh()->status)->toBe(RequestStatus::Accepted)->and(TelegramUpdateReceipt::query()->count())->toBe(1);
    Http::assertNothingSent();
});

it('exempts only the secret-protected webhook from browser CSRF validation', function (): void {
    $this->app->detectEnvironment(fn (): string => 'local');
    $this->post(route('telegram.login.store'))->assertSeeText('Page Expired');
    $this->postJson(route('telegram.webhook'), ['update_id' => 1])->assertForbidden();
    $this->postJson(route('telegram.webhook'), ['update_id' => 1], ['X-Telegram-Bot-Api-Secret-Token' => config('services.telegram.webhook_secret')])->assertOk();
});

it('prunes expired control records after seven days while keeping active confirmation and dedupe records', function (): void {
    $oldAttempt = TelegramLoginAttempt::factory()->create(['expires_at' => now()->subDays(8)]);
    $activeAttempt = TelegramLoginAttempt::factory()->create();
    $oldAction = TelegramCallbackAction::factory()->create(['expires_at' => now()->subDays(8)]);
    $activeAction = TelegramCallbackAction::factory()->create();
    $oldReceipt = TelegramUpdateReceipt::factory()->create(['created_at' => now()->subDays(8)]);
    $activeReceipt = TelegramUpdateReceipt::factory()->create();
    $this->artisan('telegram:prune-controls')->assertSuccessful();
    $this->assertModelMissing($oldAttempt);
    $this->assertModelMissing($oldAction);
    $this->assertModelMissing($oldReceipt);
    $this->assertModelExists($activeAttempt);
    $this->assertModelExists($activeAction);
    $this->assertModelExists($activeReceipt);
});

it('returns retryable failure without logging raw exceptions or persisting a partial receipt', function (): void {
    Log::spy();
    DB::listen(function (QueryExecuted $query): void {
        if (str_contains($query->sql, 'insert') && str_contains($query->sql, 'telegram_update_receipts')) {
            throw new RuntimeException('private token and payload');
        }
    });
    $payload = ['update_id' => 999, 'message' => ['from' => ['id' => 123456, 'is_bot' => false], 'chat' => ['id' => 123456, 'type' => 'private'], 'text' => '/start']];
    $this->postJson(route('telegram.webhook'), $payload, ['X-Telegram-Bot-Api-Secret-Token' => config('services.telegram.webhook_secret')])->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)->assertExactJson(['ok' => false]);
    expect(TelegramUpdateReceipt::query()->count())->toBe(0);
    Log::shouldHaveReceived('error')->with('telegram.update_failed', ['update_id' => 999, 'exception_class' => RuntimeException::class])->once();
    Http::assertNothingSent();
});
