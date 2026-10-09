<?php

declare(strict_types=1);

namespace App\Services\Couples;

use App\Models\Couple;
use Carbon\CarbonImmutable;

final class CoupleTimeline
{
    public function getDaysTogether(Couple $couple): int
    {
        $start = CarbonImmutable::parse($couple->started_on->toDateString(), $couple->timezone);
        $end = $couple->ended_at ?? CarbonImmutable::now($couple->timezone);

        return max(0, (int) $start->diffInDays($end->setTimezone($couple->timezone)->startOfDay()));
    }
}
