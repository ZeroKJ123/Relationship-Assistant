<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DateInvitation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

final class DateInvitationSeeder extends Seeder
{
    public function run(): void
    {
        if (! App::environment(['local', 'testing'])) {
            throw new \LogicException('Sample invitations are only available in development.');
        }
        DateInvitation::factory()->create();
    }
}
