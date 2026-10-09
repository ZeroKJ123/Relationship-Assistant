<?php

declare(strict_types=1);

namespace App\Services\Requests;

use LogicException;

final class RefusalTariff
{
    private const MAXIMUM_TARIFF = 65535;

    public function getVersion(): int
    {
        $version = config('economy.version');
        if (! is_int($version) || $version < 1) {
            throw new LogicException('Invalid economy settings version.');
        }

        return $version;
    }

    public function getAmount(): int
    {
        $amount = config('economy.refusal_tariff');
        if (! is_int($amount) || $amount < 0 || $amount > self::MAXIMUM_TARIFF) {
            throw new LogicException('Invalid refusal tariff.');
        }

        return $amount;
    }
}
