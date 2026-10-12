<?php

declare(strict_types=1);

namespace App\Data\Hearts;

final readonly class BalanceReconciliationData
{
    public function __construct(public int $expectedBalance, public int $expectedJournalBalance, public string $operationKey, public string $reason) {}
}
