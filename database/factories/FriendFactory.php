<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Friend;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Friend> */
final class FriendFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'name' => fake()->name(), 'operation_key' => fake()->uuid(), 'last_operation_key' => fake()->uuid(), 'version' => 0, 'archived_at' => null];
    }
}
