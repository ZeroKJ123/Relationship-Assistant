<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Movie;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Movie> */
final class MovieFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return ['user_id' => User::factory(), 'title' => $title, 'identity_hash' => hash('sha256', sprintf('%s|', mb_strtolower($title))), 'year' => null, 'description' => null, 'is_favorite' => false];
    }
}
