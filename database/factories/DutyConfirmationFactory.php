<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DutyStatus;
use App\Models\Duty;
use App\Models\DutyConfirmation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DutyConfirmation> */
final class DutyConfirmationFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['duty_id' => Duty::factory()->state(['status' => DutyStatus::Reported, 'version' => 1, 'reported_at' => now()]), 'actor_id' => fn (array $attributes): int => Duty::query()->whereKey($attributes['duty_id'])->firstOrFail()->executor_id, 'stage' => 'report', 'answer' => true, 'version' => 1];
    }
}
