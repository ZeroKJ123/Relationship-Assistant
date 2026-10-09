<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CoupleSlot;
use App\Models\Couple;
use App\Models\CoupleMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CoupleMember> */
final class CoupleMemberFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['couple_id' => Couple::factory(), 'user_id' => User::factory(), 'active_user_id' => null, 'slot' => CoupleSlot::First, 'joined_at' => now(), 'ended_at' => now()];
    }
}
