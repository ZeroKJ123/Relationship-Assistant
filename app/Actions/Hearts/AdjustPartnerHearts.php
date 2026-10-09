<?php

declare(strict_types=1);

namespace App\Actions\Hearts;

use App\Data\Hearts\AdjustmentData;
use App\Enums\HeartOperationType;
use App\Models\Couple;
use App\Models\HeartTransaction;
use App\Models\User;
use App\Services\Hearts\EconomyRules;
use App\Services\Hearts\Ledger;
use App\Services\Hearts\Limits;
use App\Services\Requests\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class AdjustPartnerHearts
{
    public function __construct(private readonly RequestContext $context, private readonly Ledger $ledger, private readonly Limits $limits) {}

    public function execute(User $actor, Couple $couple, AdjustmentData $data): HeartTransaction
    {
        Validator::make(['amount' => $data->amount, 'reason' => $data->reason, 'key' => $data->operationKey], ['amount' => ['integer', sprintf('between:1,%d', EconomyRules::MAXIMUM_SETTING)], 'reason' => ['required', 'string', 'max:500'], 'key' => ['required', 'uuid']])->validate();
        if (! in_array($data->type, [HeartOperationType::ManualThanks, HeartOperationType::ManualDeduction], true)) {
            throw ValidationException::withMessages(['hearts' => __('Выберите благодарность или списание.')]);
        }

        return DB::transaction(function () use ($actor, $couple, $data): HeartTransaction {
            $current = $this->context->lockCouple($actor, $couple);
            $partnerId = $current->members()->where('user_id', '!=', $actor->id)->lockForUpdate()->sole()->user_id;
            $partner = User::query()->findOrFail($partnerId);
            $wallet = $this->ledger->lockWallet($partner);
            $key = sprintf('manual:%s', $data->operationKey);
            $amount = $data->type === HeartOperationType::ManualThanks ? $data->amount : -$data->amount;
            if ($wallet->transactions()->where('operation_key', $key)->lockForUpdate()->first() === null && $data->amount > $this->limits->remaining($wallet, $data->type, $current->timezone)) {
                Log::warning('hearts.manual_limit_exceeded', ['actor_id' => $actor->id, 'wallet_id' => $wallet->id, 'type' => $data->type->value]);
                throw ValidationException::withMessages(['hearts' => __('Дневной или недельный лимит этой операции исчерпан.')]);
            }

            return $this->ledger->apply($partner, $amount, $data->type, $key, coupleId: $current->id, actorId: $actor->id, reason: $data->reason);
        }, attempts: 3);
    }
}
