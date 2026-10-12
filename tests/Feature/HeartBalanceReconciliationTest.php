<?php

declare(strict_types=1);

use App\Actions\Hearts\ReconcileRecordedBalance;
use App\Data\Hearts\BalanceReconciliationData;
use App\Enums\HeartOperationType;
use App\Models\AdminAudit;
use App\Models\HeartTransaction;
use App\Models\User;
use App\Services\Hearts\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    $this->admin = User::factory()->create(['login' => 'ZeroGang3']);
    config(['admin_panel.login' => 'ZeroGang3', 'admin_panel.password_hash' => Hash::make('test-only-password')]);
    $this->owner = User::factory()->create();
    $ledger = app(Ledger::class);
    $this->wallet = $ledger->getWallet($this->owner);
    $ledger->apply($this->owner, -10, HeartOperationType::ManualDeduction, 'test:deduction');
    $this->wallet->refresh();
});

it('records an explicit reconciliation without changing the chosen balance or previous entries', function (int $balance, int $difference): void {
    $previous = $this->wallet->transactions()->orderBy('id')->get();
    $snapshot = $previous->map(fn (HeartTransaction $entry): array => $entry->getRawOriginal())->all();
    $this->wallet->forceFill(['balance' => $balance])->save();
    $data = new BalanceReconciliationData($balance, 10, Str::uuid()->toString(), 'Сверка выбранного тестового баланса');
    $entry = app(ReconcileRecordedBalance::class)->execute($this->admin, $this->owner, $data);
    expect($entry->type)->toBe(HeartOperationType::BalanceReconciliation)->and($entry->amount)->toBe($difference)->and($entry->balance_after)->toBe($balance)->and($entry->actor_id)->toBe($this->admin->id);
    expect($this->wallet->refresh()->balance)->toBe($balance)->and((int) $this->wallet->transactions()->sum('amount'))->toBe($balance);
    expect(HeartTransaction::query()->whereIn('id', $previous->modelKeys())->orderBy('id')->get()->map(fn (HeartTransaction $old): array => $old->getRawOriginal())->all())->toBe($snapshot);
    expect($entry->getRawOriginal('reason'))->not->toContain('Сверка')->and(AdminAudit::query()->sole()->target_user_id)->toBe($this->owner->id);
    app(Ledger::class)->reconcile($this->owner);
    $this->asUser($this->owner)->get('/')->assertOk();
    $this->get(route('hearts.index'))->assertOk()->assertSee('Сверка тестового баланса')->assertSee('Сверка выбранного тестового баланса');
})->with([[50, 40], [20, 10], [5, -5]]);

it('reuses the same reconciliation even after a subsequent legitimate operation', function (): void {
    $this->wallet->forceFill(['balance' => 50])->save();
    $data = new BalanceReconciliationData(50, 10, Str::uuid()->toString(), 'Тестовая сверка');
    $action = app(ReconcileRecordedBalance::class);
    $entry = $action->execute($this->admin, $this->owner, $data);
    app(Ledger::class)->apply($this->owner, 1, HeartOperationType::Reward, 'test:later-reward');
    expect($action->execute($this->admin, $this->owner, $data)->id)->toBe($entry->id)->and($this->wallet->refresh()->balance)->toBe(51);
    expect(AdminAudit::query()->count())->toBe(1)->and($this->wallet->transactions()->where('type', HeartOperationType::BalanceReconciliation)->count())->toBe(1);
    expect(fn () => $action->execute($this->admin, $this->owner, new BalanceReconciliationData(50, 10, $data->operationKey, 'Другая причина')))->toThrow(ValidationException::class);
});

it('refuses stale balances and internally inconsistent history without adding a reconciliation', function (string $failure): void {
    $this->wallet->forceFill(['balance' => 50])->save();
    if ($failure === 'history') {
        HeartTransaction::factory()->create(['heart_wallet_id' => $this->wallet->id, 'amount' => 0, 'balance_after' => 9]);
    }
    $expected = $failure === 'balance' ? 49 : 50;
    expect(fn () => app(ReconcileRecordedBalance::class)->execute($this->admin, $this->owner, new BalanceReconciliationData($expected, 10, Str::uuid()->toString(), 'Тестовая сверка')))->toThrow(ValidationException::class);
    expect($this->wallet->refresh()->balance)->toBe(50)->and($this->wallet->transactions()->where('type', HeartOperationType::BalanceReconciliation)->count())->toBe(0)->and(AdminAudit::query()->count())->toBe(0);
})->with(['balance', 'history']);

it('requires the configured owner and prevents ordinary ledger operations from claiming to be a reconciliation', function (): void {
    $this->wallet->forceFill(['balance' => 50])->save();
    expect(fn () => app(ReconcileRecordedBalance::class)->execute($this->owner, $this->owner, new BalanceReconciliationData(50, 10, Str::uuid()->toString(), 'Тестовая сверка')))->toThrow(HttpException::class);
    expect(fn () => app(Ledger::class)->apply($this->owner, -1, HeartOperationType::BalanceReconciliation, 'test:unauthorized-reconciliation'))->toThrow(LogicException::class);
    expect(AdminAudit::query()->count())->toBe(0);
});

it('does not add a zero correction to an already consistent wallet and rolls back audit and history together', function (): void {
    $action = app(ReconcileRecordedBalance::class);
    expect($action->execute($this->admin, $this->owner, new BalanceReconciliationData(10, 10, Str::uuid()->toString(), 'Проверка')))->toBeNull();
    $this->wallet->forceFill(['balance' => 50])->save();
    expect(fn () => DB::transaction(function () use ($action): void {
        $action->execute($this->admin, $this->owner, new BalanceReconciliationData(50, 10, Str::uuid()->toString(), 'Тестовая сверка'));
        throw new RuntimeException('Expected rollback');
    }))->toThrow(RuntimeException::class);
    expect($this->wallet->refresh()->balance)->toBe(50)->and($this->wallet->transactions()->where('type', HeartOperationType::BalanceReconciliation)->count())->toBe(0)->and(AdminAudit::query()->count())->toBe(0);
});
