<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CoupleStatus;
use App\Enums\Currency;
use App\Models\Couple;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Couple> */
final class CoupleFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['status' => CoupleStatus::Pending, 'started_on' => now()->toDateString(), 'timezone' => Couple::DEFAULT_TIMEZONE, 'currency' => Currency::Byn];
    }
}
