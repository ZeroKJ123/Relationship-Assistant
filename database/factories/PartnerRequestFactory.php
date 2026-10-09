<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CoupleSlot;
use App\Enums\CoupleStatus;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\Couple;
use App\Models\CoupleMember;
use App\Models\PartnerRequest;
use App\Models\User;
use App\Services\Hearts\EconomyRules;
use App\Services\Requests\RequestContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/** @extends Factory<PartnerRequest> */
final class PartnerRequestFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['request_template_id' => null, 'expense_category_id' => null, 'template_name' => null, 'category_name' => null, 'heart_price' => 0, 'couple_id' => Couple::factory()->state(['status' => CoupleStatus::Active]), 'author_id' => User::factory(), 'recipient_id' => User::factory(), 'type' => RequestType::Service, 'description' => fake()->sentence(), 'due_at' => now()->addDays(3), 'is_very_wanted' => false, 'operation_key' => fake()->uuid(), 'refusal_tariff_version' => app(EconomyRules::class)->get('version'), 'refusal_tariff' => 0, 'status' => RequestStatus::Pending, 'version' => 0];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (PartnerRequest $request): void {
            DB::transaction(function () use ($request): void {
                if ($request->couple->members()->count() === 0) {
                    CoupleMember::factory()->create(['couple_id' => $request->couple_id, 'user_id' => $request->author_id, 'active_user_id' => $request->author_id, 'slot' => CoupleSlot::First, 'ended_at' => null]);
                    CoupleMember::factory()->create(['couple_id' => $request->couple_id, 'user_id' => $request->recipient_id, 'active_user_id' => $request->recipient_id, 'slot' => CoupleSlot::Second, 'ended_at' => null]);
                }
                app(RequestContext::class)->record($request->author, $request, 'created');
            });
        });
    }
}
