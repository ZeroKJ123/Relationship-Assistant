<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PartnerReminder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use LogicException;

final class PartnerReminderSeeder extends Seeder
{
    public function run(): void
    {
        if (! App::environment(['local', 'testing'])) {
            throw new LogicException('Sample care data is only available in development.');
        }
        PartnerReminder::factory()->create();
    }
}
