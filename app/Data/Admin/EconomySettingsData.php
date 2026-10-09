<?php

declare(strict_types=1);

namespace App\Data\Admin;

final readonly class EconomySettingsData
{
    /** @param array<string,int> $settings */
    public function __construct(public int $expectedVersion, public array $settings) {}
}
