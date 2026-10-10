<?php

declare(strict_types=1);

namespace App\Data\Money;

use App\Enums\Currency;
use Carbon\CarbonImmutable;

final readonly class MoneyRequestData
{
    public function __construct(public string $amount, public Currency $currency, public ?string $purpose, public CarbonImmutable $dueAt, public string $operationKey, public ?int $expenseCategoryId = null, public ?int $templateId = null) {}
}
