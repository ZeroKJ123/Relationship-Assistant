<?php

declare(strict_types=1);

namespace App\Data\Rewards;

final readonly class RewardData
{
    public function __construct(public string $title, public ?string $description, public int $heartPrice, public string $operationKey, public int $expectedVersion = 0) {}
}
