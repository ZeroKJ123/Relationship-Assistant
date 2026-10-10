<?php

declare(strict_types=1);

namespace App\Data\Friends;

final readonly class FriendData
{
    public function __construct(public string $name, public string $operationKey, public int $expectedVersion = 0) {}
}
