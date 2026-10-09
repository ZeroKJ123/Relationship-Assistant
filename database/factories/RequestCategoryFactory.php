<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RequestCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RequestCategory> */
final class RequestCategoryFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['code' => fake()->uuid(), 'name' => fake()->word(), 'sort_order' => 1];
    }
}
