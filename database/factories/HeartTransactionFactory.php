<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\HeartOperationType;
use App\Models\HeartTransaction;
use App\Models\HeartWallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HeartTransaction> */
final class HeartTransactionFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['heart_wallet_id' => HeartWallet::factory(), 'operation_key' => sprintf('fixture:%s', fake()->uuid()), 'type' => HeartOperationType::Reward, 'requested_amount' => 0, 'amount' => 0, 'balance_after' => fn (array $attributes): int => HeartWallet::query()->whereKey($attributes['heart_wallet_id'])->firstOrFail()->balance, 'actor_id' => null, 'couple_id' => null, 'duty_id' => null, 'reason' => null];
    }
}
