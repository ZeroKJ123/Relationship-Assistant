<?php

declare(strict_types=1);

namespace App\Actions\Hearts;

use App\Data\Hearts\BalanceReconciliationData;
use App\Enums\HeartOperationType;
use App\Models\AdminAudit;
use App\Models\HeartTransaction;
use App\Models\HeartWallet;
use App\Models\User;
use App\Services\Admin\PanelAccess;
use App\Services\Hearts\EconomyRules;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ReconcileRecordedBalance
{
    public function __construct(private readonly PanelAccess $access) {}

    public function execute(User $actor, User $owner, BalanceReconciliationData $data): ?HeartTransaction
    {
        abort_unless($this->access->isOwner(User::query()->findOrFail($actor->id)), Response::HTTP_FORBIDDEN);
        $range = sprintf('between:%d,%d', -EconomyRules::MAXIMUM_SETTING, EconomyRules::MAXIMUM_SETTING);
        Validator::make(['balance' => $data->expectedBalance, 'journal' => $data->expectedJournalBalance, 'amount' => $data->expectedBalance - $data->expectedJournalBalance, 'key' => $data->operationKey, 'reason' => $data->reason], ['balance' => ['integer', $range], 'journal' => ['integer', $range], 'amount' => ['integer', $range], 'key' => ['required', 'uuid'], 'reason' => ['required', 'string', 'max:500']])->validate();

        return DB::transaction(function () use ($actor, $owner, $data): ?HeartTransaction {
            User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
            $wallet = HeartWallet::query()->where('user_id', $owner->id)->lockForUpdate()->firstOrFail();
            $key = sprintf('reconciliation:wallet:%d:%s', $wallet->id, $data->operationKey);
            $existing = $wallet->transactions()->where('operation_key', $key)->first();
            if ($existing !== null) {
                $this->requireSameOperation($existing, $actor, $data);

                return $existing;
            }
            $this->requireSnapshot($wallet, $data);
            if ($data->expectedBalance === $data->expectedJournalBalance) {
                return null;
            }
            $entry = new HeartTransaction;
            $amount = $data->expectedBalance - $data->expectedJournalBalance;
            $entry->forceFill(['heart_wallet_id' => $wallet->id, 'operation_key' => $key, 'type' => HeartOperationType::BalanceReconciliation, 'requested_amount' => $amount, 'amount' => $amount, 'balance_after' => $wallet->balance, 'actor_id' => $actor->id, 'couple_id' => null, 'duty_id' => null, 'reason' => $data->reason])->save();
            $audit = new AdminAudit;
            $audit->forceFill(['admin_id' => $actor->id, 'target_user_id' => $owner->id, 'operation' => 'hearts.reconcile_recorded_balance'])->save();
            DB::afterCommit(fn () => Log::info('hearts.balance_reconciled', ['wallet_id' => $wallet->id, 'operation_id' => $entry->id, 'actor_id' => $actor->id, 'amount' => $amount, 'balance' => $wallet->balance]));

            return $entry;
        }, attempts: 3);
    }

    private function requireSnapshot(HeartWallet $wallet, BalanceReconciliationData $data): void
    {
        $latest = $wallet->transactions()->latest('id')->first();
        $journalBalance = (int) $wallet->transactions()->sum('amount');
        $startingKey = sprintf('user:%d:starting-bonus', $wallet->user_id);
        if ($wallet->balance !== $data->expectedBalance || $journalBalance !== $data->expectedJournalBalance || $latest === null || $latest->balance_after !== $journalBalance || ! $wallet->transactions()->where('operation_key', $startingKey)->exists()) {
            Log::warning('hearts.reconciliation_denied', ['wallet_id' => $wallet->id, 'reason' => 'changed_or_inconsistent_history']);
            throw ValidationException::withMessages(['hearts' => __('Баланс или журнал изменились. Повторите проверку перед сверкой.')]);
        }
    }

    private function requireSameOperation(HeartTransaction $entry, User $actor, BalanceReconciliationData $data): void
    {
        if ($entry->type !== HeartOperationType::BalanceReconciliation || $entry->actor_id !== $actor->id || $entry->requested_amount !== $data->expectedBalance - $data->expectedJournalBalance || $entry->balance_after !== $data->expectedBalance || $entry->reason !== $data->reason) {
            throw ValidationException::withMessages(['hearts' => __('Эта сверка уже записана с другими параметрами.')]);
        }
    }
}
