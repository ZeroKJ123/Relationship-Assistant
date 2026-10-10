<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvitationKind;
use App\Enums\RequestType;
use App\Models\DateInvitation;
use App\Models\PartnerRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DateInvitation> */
final class DateInvitationFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['partner_request_id' => PartnerRequest::factory()->state(['type' => RequestType::Event]), 'kind' => InvitationKind::Date, 'title' => 'Свидание', 'starts_at' => CarbonImmutable::tomorrow(), 'description' => null, 'dress_code' => null];
    }
}
