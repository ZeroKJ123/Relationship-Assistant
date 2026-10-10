<?php

declare(strict_types=1);

namespace App\Data\Catalogs;

final readonly class CarData
{
    public function __construct(public string $name, public string $fuel, public string $operationKey) {}
}
