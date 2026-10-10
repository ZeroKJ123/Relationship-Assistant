<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MoneyRequest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

final class MoneyRequestSeeder extends Seeder
{
    public function run(): void
    {
        if (! App::environment(['local', 'testing'])) {
            throw new \LogicException('Sample catalogs are only available in development.');
        }
        MoneyRequest::factory()->create();
    }
}
