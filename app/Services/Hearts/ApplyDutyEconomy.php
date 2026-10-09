<?php

declare(strict_types=1);

namespace App\Services\Hearts;

use App\Enums\HeartOperationType;
use App\Models\Duty;
use App\Models\User;

final class ApplyDutyEconomy
{
    public function __construct(private readonly Ledger $ledger, private readonly Limits $limits) {}

    public function reward(Duty $duty): void
    {
        if (! $duty->is_reward_eligible) {
            return;
        }
        $owner = User::query()->findOrFail($duty->executor_id);
        $wallet = $this->ledger->lockWallet($owner);
        $key = sprintf('duty:%d:reward', $duty->id);
        if ($wallet->transactions()->where('operation_key', $key)->lockForUpdate()->first() !== null) {
            return;
        }
        $amount = min($duty->reward, $this->limits->remaining($wallet, HeartOperationType::Reward, $duty->timezone));
        $this->ledger->apply($owner, $amount, HeartOperationType::Reward, $key, coupleId: $duty->couple_id, actorId: $duty->reviewer_id, dutyId: $duty->id);
    }

    public function overdue(Duty $duty): void
    {
        if ($duty->overdue_at !== null) {
            return;
        }
        $duty->overdue_at = now()->toImmutable();
        $this->ledger->apply(User::query()->findOrFail($duty->executor_id), -$duty->overdue_penalty, HeartOperationType::OverduePenalty, sprintf('duty:%d:overdue', $duty->id), coupleId: $duty->couple_id, dutyId: $duty->id);
    }

    public function falseReport(Duty $duty): void
    {
        $this->ledger->apply(User::query()->findOrFail($duty->executor_id), -$duty->false_report_penalty, HeartOperationType::FalseReportPenalty, sprintf('duty:%d:false-report', $duty->id), coupleId: $duty->couple_id, actorId: $duty->reviewer_id, dutyId: $duty->id);
    }
}
