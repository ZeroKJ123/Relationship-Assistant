<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EconomySettingsVersion;
use App\Services\Hearts\EconomySettingsStore;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EconomySettingsVersion> */
final class EconomySettingsVersionFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        $values = app(EconomySettingsStore::class)->read();
        unset($values['version']);

        return ['version' => (EconomySettingsVersion::query()->max('version') ?? 0) + 1, 'settings' => $values, 'created_by_id' => null];
    }
}
