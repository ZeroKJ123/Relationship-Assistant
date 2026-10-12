<?php

declare(strict_types=1);

namespace App\Data\Admin;

final readonly class BalanceAdjustmentData
{
    public function __construct(public int $amount, public string $operationKey, public string $reason) {}
}
