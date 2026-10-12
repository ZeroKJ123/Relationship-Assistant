<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Couple;
use App\Models\MemorableDateNotice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MemorableDateNotice> */
final class MemorableDateNoticeFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['couple_id' => Couple::factory(), 'user_id' => User::factory(), 'source_key' => 'started', 'title' => fake()->sentence(2), 'occurs_on' => fake()->date(), 'days_before' => 0, 'years' => null];
    }
}
