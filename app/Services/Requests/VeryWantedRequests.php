<?php

declare(strict_types=1);

namespace App\Services\Requests;

use App\Models\Couple;
use App\Models\PartnerRequest;
use App\Models\User;
use App\Services\Hearts\AvailableBalance;
use App\Services\Hearts\Ledger;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class VeryWantedRequests
{
    public function __construct(private readonly RequestContext $context, private readonly Ledger $ledger, private readonly AvailableBalance $available) {}

    public function requireAvailability(User $actor, Couple $couple): void
    {
        $limit = $couple->agreement()->lockForUpdate()->first()?->active_weekly_limit;
        if ($limit === null) {
            $this->context->deny('missing_weekly_agreement', coupleId: $couple->id);
        }
        $wallet = $this->ledger->lockWallet($actor);
        if ($this->available->get($wallet) < 0) {
            $this->context->deny('negative_balance', coupleId: $couple->id);
        }
        $week = CarbonImmutable::now($couple->timezone)->startOfWeek(CarbonInterface::MONDAY);
        $requests = PartnerRequest::query()->where('couple_id', $couple->id)->where('author_id', $actor->id)->where('is_very_wanted', true)->where('created_at', '>=', $week->utc())->where('created_at', '<', $week->addWeek()->utc())->lockForUpdate()->get(['id']);
        if ($requests->count() >= $limit) {
            $this->context->deny('weekly_quota_exhausted', coupleId: $couple->id);
        }
    }
}
