<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SelectionStatus;
use App\Models\ChoreDraw;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ChoreDraw> */
final class ChoreDrawFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'spin_started_at' => null, 'couple_id' => Couple::factory(), 'first_user_id' => User::factory(), 'second_user_id' => User::factory(), 'operation_key' => fake()->uuid(), 'description' => fake()->sentence(), 'due_at' => now()->addDay(), 'status' => SelectionStatus::Waiting, 'first_ready' => false, 'second_ready' => false, 'executor_id' => null, 'partner_request_id' => null];
    }
}
