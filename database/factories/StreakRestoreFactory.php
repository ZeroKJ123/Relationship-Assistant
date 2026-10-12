<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Couple;
use App\Models\StreakRestore;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StreakRestore> */
final class StreakRestoreFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['couple_id' => Couple::factory(), 'user_id' => User::factory(), 'restored_on' => fake()->date()];
    }
}
