<?php

declare(strict_types=1);

namespace App\Data\Couples;

final readonly class RelationshipDatesData
{
    public function __construct(public string $startedOn, public ?string $marriedOn, public int $expectedVersion) {}
}
