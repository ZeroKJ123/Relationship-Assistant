<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Couple;
use App\Models\MoodPrompt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MoodPrompt> */
final class MoodPromptFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['is_manual' => false, 'couple_id' => Couple::factory(), 'user_id' => User::factory(), 'scheduled_at' => CarbonImmutable::now()->subHour(), 'expires_at' => CarbonImmutable::now()->addHour(), 'answered_at' => null];
    }
}
