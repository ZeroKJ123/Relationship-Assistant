<?php

declare(strict_types=1);

namespace App\Data\Couples;

final readonly class WeeklyLimitData
{
    public function __construct(public int $weeklyLimit, public int $expectedVersion) {}
}
