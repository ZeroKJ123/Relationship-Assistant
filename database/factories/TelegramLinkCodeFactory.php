<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TelegramLinkCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<TelegramLinkCode> */
final class TelegramLinkCodeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $telegramId = (string) fake()->unique()->numberBetween(100000, 999999999);

        return ['identity_hash' => hash('sha256', $telegramId), 'code_hash' => hash('sha256', Str::uuid()->toString()), 'identity' => ['telegram_id' => $telegramId], 'expires_at' => now()->addMinutes(10), 'consumed_at' => null];
    }
}
