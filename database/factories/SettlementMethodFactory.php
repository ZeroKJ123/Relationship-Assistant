<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SettlementMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SettlementMethod> */
final class SettlementMethodFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['code' => fake()->uuid(), 'user_id' => User::factory(), 'name' => fake()->word(), 'details' => fake()->sentence(), 'kind' => 'custom', 'options' => [], 'sort_order' => 1];
    }
}
