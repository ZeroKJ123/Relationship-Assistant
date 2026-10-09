<?php

declare(strict_types=1);

namespace App\Data\Duties;

final readonly class DutyAnswerData
{
    public function __construct(public int $expectedVersion, public bool $answer) {}
}
