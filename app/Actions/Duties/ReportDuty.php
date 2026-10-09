<?php

declare(strict_types=1);

namespace App\Actions\Duties;

use App\Enums\DutyStatus;
use App\Models\Couple;
use App\Models\Duty;
use App\Models\User;
use App\Services\Duties\DutyContext;
use App\Services\Hearts\ApplyDutyEconomy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ReportDuty
{
    public function __construct(private readonly DutyContext $context, private readonly ApplyDutyEconomy $economy) {}

    public function execute(User $actor, Couple $couple, Duty $duty, int $version, bool $isDone): void
    {
        Log::debug('duty.report_started', ['actor_id' => $actor->id, 'duty_id' => $duty->id, 'expected_version' => $version]);
        DB::transaction(function () use ($actor, $couple, $duty, $version, $isDone): void {
            $current = $this->context->lock($actor, $couple, $duty, 'report');
            if ($this->context->isRepeated($current, $actor, $version, 'report', $isDone)) {
                return;
            }
            $this->context->requireVersion($current, $version);
            if (! in_array($current->status, [DutyStatus::Pending, DutyStatus::Overdue], true)) {
                $this->context->deny($current, 'invalid_status');
            }
            if (! $current->due_at->isFuture()) {
                $this->economy->overdue($current);
                $current->status = DutyStatus::Overdue;
            }
            if ($isDone) {
                $current->status = DutyStatus::Reported;
                $current->reported_at = now()->toImmutable();
            }
            $current->version++;
            $current->save();
            $this->context->record($current, $actor, 'report', $isDone);
        }, attempts: 3);
    }
}
