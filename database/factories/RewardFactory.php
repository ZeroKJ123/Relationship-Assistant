<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Reward;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Reward> */
final class RewardFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'title' => fake()->sentence(3), 'description' => null, 'heart_price' => fake()->numberBetween(1, 50), 'operation_key' => fake()->uuid(), 'last_operation_key' => fake()->uuid(), 'version' => 0, 'archived_at' => null];
    }
}
