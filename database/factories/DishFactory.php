<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\DishKind;
use App\Models\Dish;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Dish> */
final class DishFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'operation_key' => fake()->uuid(), 'last_operation_key' => fake()->uuid(), 'title' => fake()->words(3, true), 'ingredients' => fake()->sentence(), 'estimate' => '12.30', 'currency' => Currency::Byn, 'kind' => DishKind::Ordinary, 'portions' => 2, 'heart_price' => 0, 'photo_path' => null, 'photo_hash' => null, 'version' => 0];
    }
}
