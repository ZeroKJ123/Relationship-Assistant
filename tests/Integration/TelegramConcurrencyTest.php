<?php

declare(strict_types=1);

use App\Actions\Requests\CreatePartnerRequest;
use App\Data\Requests\NewRequestData;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Enums\TelegramActionStage;
use App\Enums\TelegramLoginStatus;
use App\Enums\TelegramOperation;
use App\Models\TelegramAccount;
use App\Models\TelegramCallbackAction;
use App\Models\TelegramLinkCode;
use App\Models\TelegramLoginAttempt;
use App\Models\TelegramUpdateReceipt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\Support\AgreementScenario;
use Tests\Support\ConcurrentOperations;

uses(DatabaseMigrations::class);

it('allows only one concurrent UUID claim for one identity or one application account', function (string $case): void {
    $pair = AgreementScenario::create();
    TelegramLinkCode::factory()->create(['code_hash' => hash('sha256', '00000000-0000-4000-8000-000000000001')]);
    $secondUser = $pair->second;
    $secondCode = 1;
    if ($case === 'user') {
        $secondUser = $pair->first;
        $secondCode = 2;
        TelegramLinkCode::factory()->create(['code_hash' => hash('sha256', '00000000-0000-4000-8000-000000000002')]);
    }
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [[(string) $pair->first->id, (string) $pair->couple->id, 'telegram_code', '1', '0'], [(string) $secondUser->id, (string) $pair->couple->id, 'telegram_code', (string) $secondCode, '0']]);
    expect($results)->toContain('accepted', 'rejected');
    expect(TelegramAccount::query()->count())->toBe(1);
    expect(TelegramLinkCode::query()->whereNotNull('consumed_at')->count())->toBe(1);
})->with(['identity', 'user']);

it('consumes a browser attempt only once in concurrent login completions', function (): void {
    $account = TelegramAccount::factory()->create();
    $attempt = TelegramLoginAttempt::factory()->create(['browser_hash' => hash('sha256', 'test-browser'), 'status' => TelegramLoginStatus::Confirmed, 'identity' => ['telegram_id' => $account->telegram_id, 'name' => 'Анна']]);
    $results = (new ConcurrentOperations)->run('telegram-auth-worker.php', [[(string) $attempt->id, 'test-browser'], [(string) $attempt->id, 'test-browser']]);
    expect($results)->toContain('accepted', 'rejected')->and(User::query()->count())->toBe(1)->and(TelegramAccount::query()->count())->toBe(1);
});

it('creates one account for two concurrently confirmed attempts for the same identity', function (): void {
    $attempts = TelegramLoginAttempt::factory()->count(2)->create(['browser_hash' => hash('sha256', 'test-browser'), 'status' => TelegramLoginStatus::Confirmed, 'identity' => ['telegram_id' => '123456', 'name' => 'Анна']]);
    try {
        $results = (new ConcurrentOperations)->run('telegram-auth-worker.php', $attempts->map(fn (TelegramLoginAttempt $attempt): array => [(string) $attempt->id, 'test-browser'])->all());
        expect($results)->toBe(['accepted', 'accepted'])->and(User::query()->count())->toBe(1)->and(TelegramAccount::query()->count())->toBe(1);
    } finally {
        // Remove test-only nullable users before the existing migration's guarded rollback.
        DB::table('telegram_accounts')->delete();
        DB::table('heart_transactions')->delete();
        DB::table('heart_wallets')->delete();
        DB::table('users')->delete();
    }
});

it('deduplicates concurrent callback updates atomically with the shared request action', function (): void {
    $pair = AgreementScenario::create();
    $account = TelegramAccount::factory()->create(['user_id' => $pair->second->id]);
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Service, 'Race request', CarbonImmutable::tomorrow()));
    $action = TelegramCallbackAction::factory()->create(['telegram_account_id' => $account->id, 'couple_id' => $pair->couple->id, 'source_id' => $request->id, 'source_version' => 0, 'stage' => TelegramActionStage::Ready, 'operation' => TelegramOperation::Agree]);
    $action->forceFill(['token_hash' => hash('sha256', str_pad((string) $action->id, 40, 'x'))])->save();
    $arguments = [(string) $pair->second->id, (string) $pair->couple->id, 'telegram_callback', (string) $action->id, '987654'];
    $results = (new ConcurrentOperations)->run('agreement-worker.php', [$arguments, $arguments]);
    expect($results)->toBe(['accepted', 'accepted'])->and($request->refresh()->status)->toBe(RequestStatus::Accepted)->and($request->version)->toBe(1)->and($request->duties()->count())->toBe(1)->and($request->events()->count())->toBe(2)->and(TelegramUpdateReceipt::query()->count())->toBe(1);
});

it('serializes website and bot responses without duplicate duties or events', function (): void {
    $pair = AgreementScenario::create();
    $account = TelegramAccount::factory()->create(['user_id' => $pair->second->id]);
    $request = app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Service, 'Race request', CarbonImmutable::tomorrow()));
    $action = TelegramCallbackAction::factory()->create(['telegram_account_id' => $account->id, 'couple_id' => $pair->couple->id, 'source_id' => $request->id, 'source_version' => 0, 'stage' => TelegramActionStage::Ready, 'operation' => TelegramOperation::Agree]);
    $action->forceFill(['token_hash' => hash('sha256', str_pad((string) $action->id, 40, 'x'))])->save();
    (new ConcurrentOperations)->run('agreement-worker.php', [[(string) $pair->second->id, (string) $pair->couple->id, 'telegram_callback', (string) $action->id, '987655'], [(string) $pair->second->id, (string) $pair->couple->id, 'request_agree', (string) $request->id, '0']]);
    expect($request->refresh()->status)->toBe(RequestStatus::Accepted)->and($request->version)->toBe(1)->and($request->duties()->count())->toBe(1)->and($request->events()->count())->toBe(2);
});
