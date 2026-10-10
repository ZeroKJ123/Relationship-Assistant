<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Friend;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

final class FriendSeeder extends Seeder
{
    public function run(): void
    {
        if (! App::environment(['local', 'testing'])) {
            throw new \LogicException('Sample invitations are only available in development.');
        }
        Friend::factory()->create();
    }
}
