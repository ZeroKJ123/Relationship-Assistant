<?php

declare(strict_types=1);

namespace App\Data\Dishes;

use App\Enums\Currency;
use App\Enums\DishKind;
use Illuminate\Http\UploadedFile;

final readonly class DishData
{
    public function __construct(public string $title, public string $ingredients, public string $estimate, public Currency $currency, public DishKind $kind, public int $portions, public int $heartPrice, public string $operationKey, public int $expectedVersion = 0, public ?UploadedFile $photo = null) {}
}
