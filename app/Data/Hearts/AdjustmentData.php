<?php

declare(strict_types=1);

namespace App\Data\Hearts;

use App\Enums\HeartOperationType;

final readonly class AdjustmentData
{
    public function __construct(public HeartOperationType $type, public int $amount, public string $reason, public string $operationKey) {}
}
