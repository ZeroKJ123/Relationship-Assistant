<?php

declare(strict_types=1);

namespace App\Data\Couples;

use Carbon\CarbonImmutable;

final readonly class CoupleSetupData
{
    public function __construct(public CarbonImmutable $startedOn, public string $timezone) {}
}
