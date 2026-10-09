<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Hearts\EconomySettingsStore;
use Illuminate\Database\Seeder;

final class EconomySettingsSeeder extends Seeder
{
    public function run(): void
    {
        app(EconomySettingsStore::class)->initialize();
    }
}
