<?php

declare(strict_types=1);

namespace App\Services\Hearts;

use App\Enums\HeartOperationType;
use App\Models\HeartTransaction;
use App\Models\HeartWallet;
use App\Models\User;
use App\Services\Admin\PanelAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

final class Ledger
{
    private const MAXIMUM_OPERATION = EconomyRules::MAXIMUM_SETTING;

    public function __construct(private readonly EconomyRules $rules, private readonly PanelAccess $panelAccess) {}

    public function getWallet(User $owner): HeartWallet
    {
        return DB::transaction(fn (): HeartWallet => $this->lockWallet($owner), attempts: 3);
    }

    public function lockWallet(User $owner): HeartWallet
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Wallet locks require a transaction.');
        }
        User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
        $wallet = HeartWallet::query()->where('user_id', $owner->id)->lockForUpdate()->first();
        if ($wallet === null) {
            $wallet = new HeartWallet;
            $wallet->forceFill(['user_id' => $owner->id, 'balance' => 0])->save();
        }
        $this->assertLatestBalance($wallet);
        $key = sprintf('user:%d:starting-bonus', $owner->id);
        if ($wallet->transactions()->where('operation_key', $key)->lockForUpdate()->first() === null) {
            $this->write($wallet, $this->rules->get('starting_bonus'), HeartOperationType::StartingBonus, $key);
        }

        return $wallet;
    }

    public function apply(User $owner, int $amount, HeartOperationType $type, string $operationKey, ?int $coupleId = null, ?int $actorId = null, ?int $dutyId = null, ?string $reason = null): HeartTransaction
    {
        Log::debug('hearts.operation_started', ['user_id' => $owner->id, 'type' => $type->value, 'requested_amount' => $amount]);
        Validator::make(['amount' => $amount, 'key' => $operationKey], ['amount' => ['integer', sprintf('between:%d,%d', -self::MAXIMUM_OPERATION, self::MAXIMUM_OPERATION)], 'key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9:_-]+$/i']])->validate();
        if ($type === HeartOperationType::StartingBonus) {
            throw new LogicException('Starting bonus is managed by wallet initialization.');
        }
        if ($type === HeartOperationType::BalanceReconciliation) {
            throw new LogicException('Balance reconciliation must use its dedicated action.');
        }
        if ($type === HeartOperationType::AdminAdjustment) {
            $actor = $actorId === null ? null : User::query()->find($actorId);
            if ($actor === null || request()->user()?->getAuthIdentifier() !== $actorId || ! $this->panelAccess->isUnlocked(request(), $actor)) {
                throw new LogicException('Administrative adjustments require an unlocked panel.');
            }
        }
        $isCredit = in_array($type, [HeartOperationType::Reward, HeartOperationType::ManualThanks], true);
        if ($type !== HeartOperationType::AdminAdjustment && (($isCredit && $amount < 0) || (! $isCredit && $amount > 0))) {
            throw new LogicException('Operation amount has the wrong sign.');
        }

        return DB::transaction(function () use ($owner, $amount, $type, $operationKey, $coupleId, $actorId, $dutyId, $reason): HeartTransaction {
            $wallet = $this->lockWallet($owner);
            $existing = HeartTransaction::query()->where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->heart_wallet_id !== $wallet->id || $existing->requested_amount !== $amount || $existing->type !== $type || $existing->actor_id !== $actorId || $existing->couple_id !== $coupleId || $existing->duty_id !== $dutyId || $existing->reason !== $reason) {
                    throw ValidationException::withMessages(['hearts' => __('Этот ключ операции уже использован. Обновите страницу.')]);
                }

                return $existing;
            }

            return $this->write($wallet, $amount, $type, $operationKey, coupleId: $coupleId, actorId: $actorId, dutyId: $dutyId, reason: $reason);
        }, attempts: 3);
    }

    public function reconcile(User $owner): void
    {
        DB::transaction(function () use ($owner): void {
            $this->assertReconciled($this->lockWallet($owner));
        }, attempts: 3);
    }

    private function assertLatestBalance(HeartWallet $wallet): void
    {
        $latest = $wallet->transactions()->latest('id')->lockForUpdate()->first();
        if (($latest === null ? 0 : $latest->balance_after) !== $wallet->balance) {
            Log::error('hearts.balance_mismatch', ['wallet_id' => $wallet->id]);
            throw new LogicException('Wallet balance does not match its last operation.');
        }
    }

    private function assertReconciled(HeartWallet $wallet): void
    {
        $entries = $wallet->transactions()->lockForUpdate()->get(['amount']);
        if ($entries->sum('amount') !== $wallet->balance) {
            Log::error('hearts.balance_mismatch', ['wallet_id' => $wallet->id]);
            throw new LogicException('Wallet balance does not match its history.');
        }
    }

    private function write(HeartWallet $wallet, int $requestedAmount, HeartOperationType $type, string $key, ?int $coupleId = null, ?int $actorId = null, ?int $dutyId = null, ?string $reason = null): HeartTransaction
    {
        $amount = $requestedAmount;
        if ($amount > 0 && $type !== HeartOperationType::AdminAdjustment) {
            $amount = min($amount, max(0, $this->rules->get('positive_cap') - $wallet->balance));
        }
        $wallet->balance += $amount;
        $wallet->save();
        $entry = new HeartTransaction;
        $entry->forceFill(['heart_wallet_id' => $wallet->id, 'operation_key' => $key, 'type' => $type, 'requested_amount' => $requestedAmount, 'amount' => $amount, 'balance_after' => $wallet->balance, 'actor_id' => $actorId, 'couple_id' => $coupleId, 'duty_id' => $dutyId, 'reason' => $reason])->save();
        DB::afterCommit(fn () => Log::info('hearts.applied', ['operation_id' => $entry->id, 'wallet_id' => $wallet->id, 'type' => $type->value, 'amount' => $amount, 'balance' => $entry->balance_after]));

        return $entry;
    }
}
