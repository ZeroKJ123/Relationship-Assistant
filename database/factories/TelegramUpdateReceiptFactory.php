<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TelegramUpdateReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<TelegramUpdateReceipt> */
final class TelegramUpdateReceiptFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['update_id' => fake()->unique()->numberBetween(1, 999999999), 'payload_hash' => hash('sha256', Str::random(40)), 'reply' => null];
    }
}
