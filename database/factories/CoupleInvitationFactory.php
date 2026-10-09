<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvitationStatus;
use App\Models\Couple;
use App\Models\CoupleInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CoupleInvitation> */
final class CoupleInvitationFactory extends Factory
{
    private const TOKEN_LENGTH = 64;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['couple_id' => Couple::factory(), 'inviter_id' => User::factory(), 'token_hash' => hash('sha256', Str::random(self::TOKEN_LENGTH)), 'status' => InvitationStatus::Pending, 'expires_at' => now()->addWeek()];
    }
}
