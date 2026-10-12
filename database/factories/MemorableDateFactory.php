<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Couple;
use App\Models\MemorableDate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MemorableDate> */
final class MemorableDateFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['couple_id' => Couple::factory(), 'created_by_id' => User::factory(), 'operation_key' => fake()->uuid(), 'title' => fake()->sentence(2), 'occurs_on' => fake()->date(), 'is_yearly' => true];
    }
}
