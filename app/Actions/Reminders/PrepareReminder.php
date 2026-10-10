<?php

declare(strict_types=1);

namespace App\Actions\Reminders;

use App\Enums\CoupleStatus;
use App\Enums\NotificationKind;
use App\Enums\ReminderStatus;
use App\Models\PartnerReminder;
use App\Services\Notifications\NotificationOutbox;
use App\Services\Reminders\ReminderLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class PrepareReminder
{
    public function __construct(private readonly ReminderLifecycle $lifecycle, private readonly NotificationOutbox $outbox) {}

    public function execute(PartnerReminder $reminder, int $version): PartnerReminder
    {
        return DB::transaction(function () use ($reminder, $version): PartnerReminder {
            $current = $this->lifecycle->lockReminder($reminder);
            Log::debug('reminder.due_checked', ['reminder_id' => $current->id, 'version' => $current->version]);
            if ($current->version !== $version || $current->status !== ReminderStatus::Scheduled || $current->due_at->isFuture()) {
                return $current;
            }
            if ($current->couple->status !== CoupleStatus::Active) {
                $this->lifecycle->transition($current, ReminderStatus::Cancelled);

                return $current;
            }
            $status = $current->due_at->lessThan(now()->subHours(PartnerReminder::MAXIMUM_DELAY_HOURS)) ? ReminderStatus::Expired : ReminderStatus::Ready;
            $this->lifecycle->transition($current, $status);
            if ($status === ReminderStatus::Ready) {
                $this->outbox->record($current->couple_id, $current->recipient_id, NotificationKind::Reminder, $current->id, $current->version, $current->due_at->addHours(PartnerReminder::MAXIMUM_DELAY_HOURS));
            }

            return $current;
        }, attempts: 3);
    }
}
