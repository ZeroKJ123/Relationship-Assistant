<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SelectionStatus;
use App\Models\Couple;
use App\Models\MovieChoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MovieChoice> */
final class MovieChoiceFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['couple_id' => Couple::factory(), 'first_user_id' => User::factory(), 'second_user_id' => User::factory(), 'operation_key' => fake()->uuid(), 'mode' => 'swipe', 'status' => SelectionStatus::Waiting, 'first_ready' => false, 'second_ready' => false, 'pool' => [], 'votes' => [], 'selected_movie_id' => null];
    }
}
