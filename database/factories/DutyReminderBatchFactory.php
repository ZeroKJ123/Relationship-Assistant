<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Couple;
use App\Models\DutyReminderBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DutyReminderBatch> */
final class DutyReminderBatchFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['couple_id' => Couple::factory(), 'user_id' => User::factory(), 'scheduled_at' => now()->startOfHour()];
    }
}
