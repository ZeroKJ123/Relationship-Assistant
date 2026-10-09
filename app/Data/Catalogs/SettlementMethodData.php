<?php

declare(strict_types=1);

namespace App\Data\Catalogs;

final readonly class SettlementMethodData
{
    public function __construct(public string $name, public string $details, public string $key) {}
}
