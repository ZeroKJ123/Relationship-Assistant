<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RequestType;
use App\Models\GuestVisit;
use App\Models\PartnerRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GuestVisit> */
final class GuestVisitFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['partner_request_id' => PartnerRequest::factory()->state(['type' => RequestType::Guests]), 'guests_snapshot' => [['id' => 1, 'version' => 0, 'name' => 'Гость']], 'starts_at' => CarbonImmutable::tomorrow(), 'ends_at' => CarbonImmutable::tomorrow()->addHour(), 'duration_minutes' => 60, 'reason' => 'Встреча'];
    }
}
