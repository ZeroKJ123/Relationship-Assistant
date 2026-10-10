<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReminderStatus;
use App\Models\Couple;
use App\Models\PartnerReminder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PartnerReminder> */
final class PartnerReminderFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['couple_id' => Couple::factory(), 'author_id' => User::factory(), 'recipient_id' => User::factory(), 'text' => 'Напомнить о встрече', 'due_at' => CarbonImmutable::tomorrow(), 'operation_key' => fake()->uuid(), 'status' => ReminderStatus::Scheduled, 'version' => 0, 'cancelled_at' => null, 'prepared_at' => null];
    }
}
