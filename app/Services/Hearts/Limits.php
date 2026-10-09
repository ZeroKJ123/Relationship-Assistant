<?php

declare(strict_types=1);

namespace App\Services\Hearts;

use App\Enums\HeartOperationType;
use App\Models\HeartWallet;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use LogicException;

final class Limits
{
    public function __construct(private readonly EconomyRules $rules) {}

    public function remaining(HeartWallet $wallet, HeartOperationType $type, string $timezone): int
    {
        $prefix = match ($type) {
            HeartOperationType::Reward => 'reward',HeartOperationType::ManualThanks => 'manual_thanks',HeartOperationType::ManualDeduction => 'manual_deduction', default => throw new LogicException('This operation has no spending limits.')
        };
        $day = CarbonImmutable::now($timezone)->startOfDay();
        $week = CarbonImmutable::now($timezone)->startOfWeek(CarbonInterface::MONDAY);
        $entries = $wallet->transactions()->where('type', $type)->where('created_at', '>=', $week->utc())->where('created_at', '<', $week->addWeek()->utc())->lockForUpdate()->get();
        $weeklyUsed = 0;
        $dailyUsed = 0;
        foreach ($entries as $entry) {
            $weeklyUsed += abs($entry->amount);
            if ($entry->created_at->greaterThanOrEqualTo($day->utc()) && $entry->created_at->lessThan($day->addDay()->utc())) {
                $dailyUsed += abs($entry->amount);
            }
        }

        return max(0, min($this->rules->get(sprintf('%s_daily', $prefix)) - $dailyUsed, $this->rules->get(sprintf('%s_weekly', $prefix)) - $weeklyUsed));
    }
}
