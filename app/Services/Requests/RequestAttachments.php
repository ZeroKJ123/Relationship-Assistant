<?php

declare(strict_types=1);

namespace App\Services\Requests;

use App\Data\Requests\NewRequestData;
use App\Enums\DishKind;
use App\Enums\RequestType;
use App\Models\Dish;
use App\Models\MoneyRequest;
use App\Models\PartnerRequest;
use App\Models\User;
use App\Services\Hearts\AvailableBalance;
use App\Services\Hearts\Ledger;
use App\Services\Money\DecimalAmount;
use Illuminate\Validation\ValidationException;

final class RequestAttachments
{
    public function __construct(private readonly DecimalAmount $amounts, private readonly Ledger $ledger, private readonly AvailableBalance $available) {}

    /** @return array<string,mixed> */
    public function snapshot(User $actor, int $recipientId, NewRequestData $data): array
    {
        if ($data->dishId === null) {
            if ($data->dishVersion !== null) {
                $this->deny('dish_id');
            }
            $this->validateMoney($actor, $data);

            return [];
        }
        $dish = Dish::query()->whereKey($data->dishId)->lockForUpdate()->first();
        if ($dish === null || $dish->user_id !== $recipientId || $data->type !== RequestType::Food || $data->dishVersion !== $dish->version || $data->heartPrice !== $dish->heart_price || $data->moneyAmount !== null || $data->moneyCurrency !== null) {
            $this->deny('dish_id');
        }
        $this->requireSpecialAvailability($actor, $dish->kind);

        return ['dish_id' => $dish->id, 'dish_version' => $dish->version, 'dish_kind' => $dish->kind, 'dish_snapshot' => [
            'title' => $dish->title, 'ingredients' => $dish->ingredients, 'estimate' => $dish->estimate, 'currency' => $dish->currency->value, 'portions' => $dish->portions, 'photo_path' => $dish->photo_path,
        ]];
    }

    public function attachMoney(PartnerRequest $request, NewRequestData $data): void
    {
        if ($data->moneyAmount === null) {
            return;
        }
        (new MoneyRequest)->forceFill(['partner_request_id' => $request->id, 'amount' => $this->amounts->normalize($data->moneyAmount, isPositive: true), 'currency' => $data->moneyCurrency])->save();
    }

    public function isSame(PartnerRequest $request, NewRequestData $data): bool
    {
        if ($request->dish_id !== $data->dishId || $request->dish_version !== $data->dishVersion) {
            return false;
        }
        $money = $request->moneyRequest()->lockForUpdate()->first();
        if ($money === null) {
            return $data->moneyAmount === null && $data->moneyCurrency === null;
        }

        return $data->moneyAmount !== null && $money->amount === $this->amounts->normalize($data->moneyAmount, isPositive: true) && $money->currency === $data->moneyCurrency;
    }

    public function requireSpecialAvailability(User $actor, ?DishKind $kind): void
    {
        if ($kind === DishKind::Special && $this->available->get($this->ledger->lockWallet($actor)) < 0) {
            throw ValidationException::withMessages(['dish_id' => __('Особые блюда доступны только без отрицательного свободного баланса, даже если они бесплатны.')]);
        }
    }

    private function validateMoney(User $actor, NewRequestData $data): void
    {
        if ($data->moneyAmount === null && $data->moneyCurrency === null) {
            return;
        }
        if ($data->type !== RequestType::Money || $data->moneyAmount === null || $data->moneyCurrency === null || $data->isVeryWanted || $data->heartPrice !== 0) {
            $this->deny('amount');
        }
        $this->amounts->normalize($data->moneyAmount, isPositive: true);
        if (User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail()->payment_details === null) {
            throw ValidationException::withMessages(['amount' => __('Сначала сохраните реквизиты в своём профиле.')]);
        }
    }

    private function deny(string $field): never
    {
        throw ValidationException::withMessages([$field => __('Карточка изменилась или не подходит просьбе. Обновите страницу.')]);
    }
}
