<?php

declare(strict_types=1);

namespace App\Services\Requests;

use App\Data\Requests\RequestResponseData;
use App\Enums\ConditionType;
use App\Models\User;
use App\Models\WishlistItem;
use App\Services\Money\DecimalAmount;
use Illuminate\Validation\ValidationException;

final class ConditionAttachments
{
    public function __construct(private readonly DecimalAmount $amounts) {}

    /** @return array<string,mixed> */
    public function snapshot(User $actor, ConditionType $type, RequestResponseData $data): array
    {
        if ($type === ConditionType::Gift) {
            if ($data->amount !== null || $data->currency !== null) {
                $this->deny();
            }
            $item = WishlistItem::query()->whereKey($data->wishlistItemId)->lockForUpdate()->first();
            if ($item === null || $item->user_id !== $actor->id || $item->version !== $data->wishlistVersion || $item->archived_at !== null || $item->received_at !== null || $item->claimed_condition_id !== null) {
                $this->deny();
            }

            return ['wishlist_item_id' => $item->id, 'wishlist_version' => $item->version, 'gift_snapshot' => ['title' => $item->title, 'description' => $item->description, 'price' => $item->price, 'currency' => $item->currency->value, 'photo_path' => $item->photo_path]];
        }
        if ($data->wishlistItemId !== null || $data->wishlistVersion !== null) {
            $this->deny();
        }
        if ($type === ConditionType::Money) {
            if ($data->amount === null || $data->currency === null || User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail()->payment_details === null) {
                $this->deny();
            }

            return ['amount' => $this->amounts->normalize($data->amount, isPositive: true), 'currency' => $data->currency];
        }
        if ($data->amount !== null || $data->currency !== null) {
            $this->deny();
        }

        return [];
    }

    private function deny(): never
    {
        throw ValidationException::withMessages(['condition_type' => __('Проверьте сумму, валюту, реквизиты или выберите своё актуальное желание без обещанного подарка.')]);
    }
}
