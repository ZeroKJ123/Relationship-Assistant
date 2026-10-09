<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ConditionType;
use App\Enums\RequestStatus;
use App\Models\PartnerRequest;
use App\Models\RequestCondition;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RequestCondition> */
final class RequestConditionFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['partner_request_id' => PartnerRequest::factory()->state(['status' => RequestStatus::Conditional, 'version' => 1]), 'proposed_by_id' => fn (array $attributes): int => PartnerRequest::query()->whereKey($attributes['partner_request_id'])->firstOrFail()->recipient_id, 'version' => 1, 'type' => ConditionType::Action, 'description' => fake()->sentence(), 'due_at' => now()->addDays(3), 'accepted_at' => null, 'method_kind' => null, 'method_name' => null, 'sushi_size' => null];
    }
}
