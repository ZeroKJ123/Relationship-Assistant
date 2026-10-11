<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MoodLevel;
use App\Enums\MoodSource;
use App\Models\Couple;
use App\Models\MoodEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MoodEntry> */
final class MoodEntryFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['couple_id' => Couple::factory(), 'user_id' => User::factory(), 'level' => MoodLevel::Good, 'source' => MoodSource::Web, 'operation_key' => fake()->uuid(), 'recorded_at' => CarbonImmutable::now(), 'mood_prompt_id' => null, 'emoji' => null, 'sticker_path' => null, 'sticker_format' => null, 'sticker_status' => null];
    }
}
