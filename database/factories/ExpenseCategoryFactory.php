<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExpenseCategory> */
final class ExpenseCategoryFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['code' => fake()->uuid(), 'name' => fake()->word(), 'sort_order' => 1];
    }
}
