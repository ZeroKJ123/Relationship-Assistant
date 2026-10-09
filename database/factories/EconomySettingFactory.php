<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EconomySetting;
use App\Models\EconomySettingsVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EconomySetting> */
final class EconomySettingFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['current_version_id' => EconomySettingsVersion::factory()];
    }
}
