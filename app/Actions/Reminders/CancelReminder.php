<?php

declare(strict_types=1);

namespace App\Actions\Reminders;

use App\Enums\ReminderStatus;
use App\Models\PartnerReminder;
use App\Models\User;
use App\Services\Reminders\ReminderLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CancelReminder
{
    public function __construct(private readonly ReminderLifecycle $lifecycle) {}

    public function execute(User $actor, PartnerReminder $reminder, int $version): void
    {
        Gate::forUser($actor)->authorize('cancel', $reminder);
        DB::transaction(function () use ($actor, $reminder, $version): void {
            $current = $this->lifecycle->lockReminder($reminder);
            Gate::forUser($actor)->authorize('cancel', $current);
            if ($current->status === ReminderStatus::Cancelled) {
                return;
            }
            if (! $current->status->canCancel() || $current->version !== $version) {
                $this->lifecycle->deny($current);
            }
            $this->lifecycle->transition($current, ReminderStatus::Cancelled);
        }, attempts: 3);
    }
}
