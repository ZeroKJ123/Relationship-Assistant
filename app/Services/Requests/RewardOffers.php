<?php

declare(strict_types=1);

namespace App\Services\Requests;

use App\Data\Requests\NewRequestData;
use App\Enums\RequestType;
use App\Models\Reward;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class RewardOffers
{
    /**
     * Покупка награды — платная услуга по текущей цене из магазина получателя просьбы.
     * Вызывается внутри транзакции создания просьбы.
     */
    public function requireOffer(int $ownerId, NewRequestData $data): void
    {
        if ($data->rewardId === null) {
            return;
        }
        $reward = Reward::query()->whereKey($data->rewardId)->lockForUpdate()->first();
        if ($reward === null || $reward->user_id !== $ownerId || $reward->archived_at !== null || $data->type !== RequestType::Service || $data->heartPrice !== $reward->heart_price) {
            Log::warning('reward.purchase_denied', ['reward_id' => $data->rewardId, 'reason' => 'stale_or_foreign_offer']);
            throw ValidationException::withMessages(['reward' => __('Награда изменилась или больше недоступна. Откройте магазин заново.')]);
        }
    }
}
