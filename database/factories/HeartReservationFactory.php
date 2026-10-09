<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\HeartReservation;
use App\Models\PartnerRequest;
use App\Services\Hearts\Ledger;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HeartReservation> */
final class HeartReservationFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['partner_request_id' => PartnerRequest::factory()->state(['heart_price' => 5]), 'heart_wallet_id' => fn (array $attributes): int => app(Ledger::class)->getWallet(PartnerRequest::query()->whereKey($attributes['partner_request_id'])->firstOrFail()->author)->id, 'amount' => 5, 'status' => ReservationStatus::Held];
    }
}
