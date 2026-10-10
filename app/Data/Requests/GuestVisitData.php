<?php

declare(strict_types=1);

namespace App\Data\Requests;

use Carbon\CarbonImmutable;

final readonly class GuestVisitData
{
    /** @param list<array{id:int,version:int}> $friends */
    public function __construct(public array $friends, public CarbonImmutable $startsAt, public int $durationMinutes, public string $reason, public string $operationKey, public ?int $templateId = null) {}
}
