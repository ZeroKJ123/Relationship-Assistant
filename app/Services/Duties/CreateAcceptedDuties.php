<?php

declare(strict_types=1);

namespace App\Services\Duties;

use App\Enums\DutyKind;
use App\Enums\DutyStatus;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\Duty;
use App\Models\PartnerRequest;
use App\Services\Hearts\EconomyRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

final class CreateAcceptedDuties
{
    public function __construct(private readonly EconomyRules $rules) {}

    public function execute(PartnerRequest $request): void
    {
        if (DB::transactionLevel() === 0 || $request->status !== RequestStatus::Accepted) {
            throw new LogicException('Duties require a locked accepted request.');
        }
        $this->create($request, DutyKind::Service, $request->recipient_id, $request->author_id, $request->description, $request->due_at, $request->type !== RequestType::Money);
        $condition = $request->conditions()->whereNotNull('accepted_at')->lockForUpdate()->first();
        if ($condition !== null) {
            $this->create($request, DutyKind::Condition, $request->author_id, $request->recipient_id, $condition->description, $condition->due_at, true, conditionId: $condition->id);
        }
    }

    private function create(PartnerRequest $request, DutyKind $kind, int $executorId, int $reviewerId, string $description, CarbonImmutable $dueAt, bool $isRewardEligible, ?int $conditionId = null): void
    {
        if (Duty::query()->where('partner_request_id', $request->id)->where('kind', $kind)->lockForUpdate()->first() !== null) {
            return;
        }
        $duty = new Duty;
        $duty->forceFill(['partner_request_id' => $request->id, 'couple_id' => $request->couple_id, 'request_condition_id' => $conditionId, 'kind' => $kind, 'executor_id' => $executorId, 'reviewer_id' => $reviewerId, 'description' => $description, 'due_at' => $dueAt, 'timezone' => $request->couple->timezone, 'status' => DutyStatus::Pending, 'version' => 0, 'is_reward_eligible' => $isRewardEligible, 'tariff_version' => $this->rules->get('version'), 'reward' => $this->rules->get('reward'), 'overdue_penalty' => $this->rules->get('overdue_penalty'), 'false_report_penalty' => $this->rules->get('false_report_penalty')])->save();
        DB::afterCommit(fn () => Log::info('duty.created', ['duty_id' => $duty->id, 'request_id' => $request->id, 'kind' => $kind->value, 'tariff_version' => $duty->tariff_version]));
    }
}
