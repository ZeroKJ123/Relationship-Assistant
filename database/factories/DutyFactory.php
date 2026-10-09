<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DutyKind;
use App\Enums\DutyStatus;
use App\Enums\RequestStatus;
use App\Models\Couple;
use App\Models\Duty;
use App\Models\PartnerRequest;
use App\Services\Hearts\EconomyRules;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Duty> */
final class DutyFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['partner_request_id' => PartnerRequest::factory()->state(['status' => RequestStatus::Accepted, 'accepted_at' => now()]),
            'couple_id' => fn (array $attributes): int => $this->getRequest($attributes)->couple_id,
            'executor_id' => fn (array $attributes): int => $this->getRequest($attributes)->recipient_id,
            'reviewer_id' => fn (array $attributes): int => $this->getRequest($attributes)->author_id,
            'request_condition_id' => null, 'kind' => DutyKind::Service, 'description' => fn (array $attributes): string => $this->getRequest($attributes)->description, 'due_at' => fn (array $attributes): CarbonImmutable => $this->getRequest($attributes)->due_at, 'timezone' => Couple::DEFAULT_TIMEZONE, 'status' => DutyStatus::Pending, 'version' => 0, 'is_reward_eligible' => true, 'tariff_version' => app(EconomyRules::class)->get('version'), 'reward' => app(EconomyRules::class)->get('reward'), 'overdue_penalty' => app(EconomyRules::class)->get('overdue_penalty'), 'false_report_penalty' => app(EconomyRules::class)->get('false_report_penalty')];
    }

    /** @param array<string,mixed> $attributes */
    private function getRequest(array $attributes): PartnerRequest
    {
        return PartnerRequest::query()->whereKey($attributes['partner_request_id'])->firstOrFail();
    }
}
