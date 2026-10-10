<?php

declare(strict_types=1);

namespace App\Services\Requests;

use App\Enums\ConditionType;
use App\Enums\DutyKind;
use App\Models\Couple;
use App\Models\Duty;
use App\Models\PartnerRequest;
use App\Models\RequestCondition;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class AgreementAttachments
{
    public function __construct(private readonly RequestAttachments $requests) {}

    public function accept(PartnerRequest $request): void
    {
        $this->requests->requireSpecialAvailability($request->author, $request->dish_kind);
        $money = $request->moneyRequest()->lockForUpdate()->first();
        if ($money !== null) {
            $money->forceFill(['payment_details_snapshot' => $this->getPaymentDetails($request->author_id)])->save();
        }
        $condition = $request->conditions()->whereNotNull('accepted_at')->lockForUpdate()->first();
        if ($condition === null) {
            return;
        }
        if ($condition->type === ConditionType::Money) {
            $condition->forceFill(['payment_details_snapshot' => $this->getPaymentDetails($condition->proposed_by_id)])->save();
        }
        if ($condition->type === ConditionType::Gift) {
            $this->claimGift($condition);
        }
    }

    public function release(PartnerRequest $request): void
    {
        foreach ($request->conditions()->whereNotNull('wishlist_item_id')->orderBy('id')->lockForUpdate()->get() as $condition) {
            $this->releaseGift($condition);
        }
    }

    public function releaseCouple(Couple $couple): void
    {
        foreach (PartnerRequest::query()->where('couple_id', $couple->id)->orderBy('id')->lockForUpdate()->get() as $request) {
            $this->release($request);
        }
    }

    public function verified(Duty $duty, bool $isConfirmed): void
    {
        if ($duty->kind !== DutyKind::Condition || $duty->request_condition_id === null) {
            return;
        }
        $condition = RequestCondition::query()->whereKey($duty->request_condition_id)->lockForUpdate()->firstOrFail();
        if ($condition->type !== ConditionType::Gift) {
            return;
        }
        if (! $isConfirmed) {
            $this->releaseGift($condition);

            return;
        }
        $item = WishlistItem::query()->whereKey($condition->wishlist_item_id)->lockForUpdate()->firstOrFail();
        if ($item->claimed_condition_id !== $condition->id || $item->received_at !== null) {
            $this->deny();
        }
        $item->forceFill(['received_at' => now(), 'archived_at' => now(), 'claimed_condition_id' => null, 'version' => $item->version + 1])->save();
        DB::afterCommit(fn () => Log::info('wishlist.gift_received', ['item_id' => $item->id, 'condition_id' => $condition->id, 'duty_id' => $duty->id]));
    }

    private function claimGift(RequestCondition $condition): void
    {
        $item = WishlistItem::query()->whereKey($condition->wishlist_item_id)->lockForUpdate()->firstOrFail();
        if ($item->user_id !== $condition->proposed_by_id || $item->version !== $condition->wishlist_version || $item->archived_at !== null || $item->received_at !== null || $item->claimed_condition_id !== null) {
            $this->deny();
        }
        $item->forceFill(['claimed_condition_id' => $condition->id, 'version' => $item->version + 1])->save();
        DB::afterCommit(fn () => Log::info('wishlist.gift_promised', ['item_id' => $item->id, 'condition_id' => $condition->id]));
    }

    private function releaseGift(RequestCondition $condition): void
    {
        $item = WishlistItem::query()->whereKey($condition->wishlist_item_id)->lockForUpdate()->first();
        if ($item !== null && $item->claimed_condition_id === $condition->id && $item->received_at === null) {
            $item->forceFill(['claimed_condition_id' => null, 'version' => $item->version + 1])->save();
            DB::afterCommit(fn () => Log::info('wishlist.gift_released', ['item_id' => $item->id, 'condition_id' => $condition->id]));
        }
    }

    private function getPaymentDetails(int $userId): string
    {
        $details = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail()->payment_details;
        if ($details === null || trim($details) === '') {
            $this->deny();
        }

        return $details;
    }

    private function deny(): never
    {
        throw ValidationException::withMessages(['partner_request' => __('Условие изменилось, подарок уже обещан или реквизиты отсутствуют. Обновите страницу.')]);
    }
}
