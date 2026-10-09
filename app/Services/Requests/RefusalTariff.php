<?php

declare(strict_types=1);

namespace App\Services\Requests;

use App\Services\Hearts\EconomyRules;

final class RefusalTariff
{
    public function __construct(private readonly EconomyRules $rules) {}

    public function getVersion(): int
    {
        return $this->rules->get('version');
    }

    public function getAmount(): int
    {
        return $this->rules->get('refusal_tariff');
    }
}
