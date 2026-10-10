<?php

declare(strict_types=1);

namespace App\Data\Reminders;

use Carbon\CarbonImmutable;

final readonly class ReminderData
{
    public function __construct(public string $text, public CarbonImmutable $dueAt, public string $operationKey) {}
}
