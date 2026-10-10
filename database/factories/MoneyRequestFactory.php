<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\RequestType;
use App\Models\MoneyRequest;
use App\Models\PartnerRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MoneyRequest> */
final class MoneyRequestFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['partner_request_id' => PartnerRequest::factory()->state(['type' => RequestType::Money]), 'amount' => '25.50', 'currency' => Currency::Byn, 'payment_details_snapshot' => null];
    }
}
