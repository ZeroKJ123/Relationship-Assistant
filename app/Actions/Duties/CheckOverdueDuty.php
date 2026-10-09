<?php

declare(strict_types=1);

namespace App\Actions\Duties;

use App\Enums\CoupleStatus;
use App\Enums\DutyStatus;
use App\Models\Duty;
use App\Models\User;
use App\Services\Duties\DutyContext;
use App\Services\Hearts\ApplyDutyEconomy;
use Illuminate\Support\Facades\DB;

final class CheckOverdueDuty
{
    public function __construct(private readonly DutyContext $context, private readonly ApplyDutyEconomy $economy) {}

    public function execute(Duty $duty): void
    {
        DB::transaction(function () use ($duty): void {
            if ($duty->couple->status !== CoupleStatus::Active) {
                return;
            }
            $current = $this->context->lock(User::query()->findOrFail($duty->executor_id), $duty->couple, $duty, 'report');
            if ($current->status !== DutyStatus::Pending || $current->due_at->isFuture()) {
                return;
            }
            $this->economy->overdue($current);
            $current->status = DutyStatus::Overdue;
            $current->version++;
            $current->save();
            $this->context->record($current, null, 'overdue', null);
        }, attempts: 3);
    }
}
