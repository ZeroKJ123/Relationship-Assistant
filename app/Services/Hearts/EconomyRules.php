<?php

declare(strict_types=1);

namespace App\Services\Hearts;

use LogicException;

final class EconomyRules
{
    public const MAXIMUM_SETTING = 1000;

    public function __construct(private readonly EconomySettingsStore $store) {}

    public function get(string $key): int
    {
        $value = $this->store->read()[$key] ?? null;
        if (! is_int($value) || $value < 0 || ($key !== 'version' && $value > self::MAXIMUM_SETTING)) {
            throw new LogicException(sprintf('Invalid economy setting: %s', $key));
        }
        if ($key === 'version' && $value === 0) {
            throw new LogicException('Economy version must be positive.');
        }

        return $value;
    }
}
