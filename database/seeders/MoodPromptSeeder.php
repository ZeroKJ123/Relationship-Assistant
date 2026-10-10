<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MoodPrompt;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use LogicException;

final class MoodPromptSeeder extends Seeder
{
    public function run(): void
    {
        if (! App::environment(['local', 'testing'])) {
            throw new LogicException('Sample care data is only available in development.');
        }
        MoodPrompt::factory()->create();
    }
}
