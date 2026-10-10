<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WishlistItem> */
final class WishlistItemFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'operation_key' => fake()->uuid(), 'last_operation_key' => fake()->uuid(), 'title' => fake()->words(3, true), 'description' => null, 'price' => '49.99', 'currency' => Currency::Byn, 'photo_path' => null, 'photo_hash' => null, 'version' => 0, 'archived_at' => null, 'received_at' => null, 'claimed_condition_id' => null];
    }
}
