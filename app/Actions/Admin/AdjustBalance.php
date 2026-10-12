<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Data\Admin\BalanceAdjustmentData;
use App\Enums\HeartOperationType;
use App\Models\AdminAudit;
use App\Models\HeartTransaction;
use App\Models\User;
use App\Services\Admin\PanelAccess;
use App\Services\Hearts\AvailableBalance;
use App\Services\Hearts\EconomyRules;
use App\Services\Hearts\Ledger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class AdjustBalance
{
    public function __construct(private readonly PanelAccess $access, private readonly Ledger $ledger, private readonly AvailableBalance $available) {}

    public function execute(User $actor, User $owner, BalanceAdjustmentData $data, Request $request): HeartTransaction
    {
        abort_unless($this->access->isUnlocked($request, $actor), Response::HTTP_FORBIDDEN);
        Validator::make(['amount' => $data->amount, 'key' => $data->operationKey, 'reason' => $data->reason], ['amount' => ['integer', 'not_in:0', sprintf('between:%d,%d', -EconomyRules::MAXIMUM_SETTING, EconomyRules::MAXIMUM_SETTING)], 'key' => ['required', 'uuid'], 'reason' => ['required', 'string', 'max:500']])->validate();

        return DB::transaction(function () use ($actor, $owner, $data): HeartTransaction {
            $wallet = $this->ledger->lockWallet($owner);
            $key = sprintf('admin-balance:%s', $data->operationKey);
            $existing = HeartTransaction::query()->where('operation_key', $key)->first();
            if ($existing === null) {
                $reserved = $this->available->reserved($wallet);
                if ($data->amount < 0 && $reserved > 0 && $wallet->balance + $data->amount < $reserved) {
                    throw ValidationException::withMessages(['amount' => __('Нельзя списать сердечки, зарезервированные для принятых услуг.')]);
                }
                if (abs($wallet->balance + $data->amount) > EconomyRules::MAXIMUM_SETTING) {
                    throw ValidationException::withMessages(['amount' => __('Итоговый баланс выходит за допустимые границы.')]);
                }
            }
            $entry = $this->ledger->apply($owner, $data->amount, HeartOperationType::AdminAdjustment, $key, actorId: $actor->id, reason: $data->reason);
            if ($existing === null) {
                $audit = new AdminAudit;
                $audit->forceFill(['admin_id' => $actor->id, 'target_user_id' => $owner->id, 'operation' => sprintf('hearts.admin_adjustment:%d', $entry->id)])->save();
            }

            return $entry;
        }, attempts: 3);
    }
}
