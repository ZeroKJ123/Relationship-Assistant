<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TelegramLoginStatus;
use App\Models\TelegramLoginAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<TelegramLoginAttempt> */
final class TelegramLoginAttemptFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => null, 'start_hash' => hash('sha256', Str::random(40)), 'browser_hash' => hash('sha256', Str::random(40)), 'confirmation_hash' => null, 'identity' => null, 'verification_code' => (string) fake()->numberBetween(100000, 999999), 'status' => TelegramLoginStatus::Pending, 'expires_at' => now()->addMinutes(10)];
    }
}
