<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationKind;
use App\Models\Couple;
use App\Models\MoodPrompt;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NotificationDelivery> */
final class NotificationDeliveryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['couple_id' => Couple::factory(), 'recipient_id' => User::factory(), 'kind' => NotificationKind::MoodPrompt, 'source_id' => MoodPrompt::factory(), 'source_version' => null, 'operation_key' => fake()->uuid(), 'status' => DeliveryStatus::Pending, 'attempts' => 0, 'available_at' => now(), 'expires_at' => now()->addHours(NotificationDelivery::DEFAULT_LIFETIME_HOURS)];
    }
}
