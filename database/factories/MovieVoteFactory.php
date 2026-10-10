<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Couple;
use App\Models\MovieVote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MovieVote> */
final class MovieVoteFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['couple_id' => Couple::factory(), 'user_id' => User::factory(), 'identity_hash' => hash('sha256', fake()->uuid()), 'liked' => true];
    }
}
