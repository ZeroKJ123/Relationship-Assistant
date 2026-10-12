<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Couple;
use App\Models\DateIdea;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DateIdea> */
final class DateIdeaFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['couple_id' => Couple::factory(), 'created_by_id' => User::factory(), 'operation_key' => fake()->uuid(), 'title' => fake()->sentence(2)];
    }
}
