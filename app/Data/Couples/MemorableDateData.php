<?php

declare(strict_types=1);

namespace App\Data\Couples;

use Carbon\CarbonImmutable;

final readonly class MemorableDateData
{
    public function __construct(public string $title, public CarbonImmutable $occursOn, public bool $isYearly, public string $operationKey) {}
}
