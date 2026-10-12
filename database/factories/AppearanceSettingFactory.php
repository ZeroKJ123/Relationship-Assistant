<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AppearanceSetting;
use App\Services\Appearance\AppearanceCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AppearanceSetting> */
final class AppearanceSettingFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['revision' => 0, 'settings' => (new AppearanceCatalog)->defaults()];
    }
}
