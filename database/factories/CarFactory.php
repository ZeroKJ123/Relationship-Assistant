<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Car;
use App\Models\SettlementMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Car> */
final class CarFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'settlement_method_id' => SettlementMethod::factory(), 'name' => 'Toyota', 'fuel' => 'petrol', 'operation_key' => fake()->uuid()];
    }
}
