<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HeartWallet;
use App\Models\User;
use App\Services\Hearts\Ledger;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HeartWallet> */
final class HeartWalletFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'balance' => 0];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (HeartWallet $wallet): void {
            app(Ledger::class)->getWallet(User::query()->findOrFail($wallet->user_id));
            $wallet->refresh();
        });
    }
}
