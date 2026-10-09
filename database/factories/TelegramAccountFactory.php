<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TelegramAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TelegramAccount> */
final class TelegramAccountFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $telegramId = (string) fake()->unique()->numberBetween(100000, 999999999);

        return ['user_id' => User::factory(), 'telegram_id' => $telegramId, 'chat_id' => $telegramId];
    }
}
