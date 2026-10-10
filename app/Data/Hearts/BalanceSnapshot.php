<?php

declare(strict_types=1);

namespace App\Data\Hearts;

final readonly class BalanceSnapshot
{
    public function __construct(public int $balance, public int $reserved, public int $available) {}
}
