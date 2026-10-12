<?php

declare(strict_types=1);

namespace App\Actions\Duties;

use App\Enums\DutyStatus;
use App\Enums\NotificationKind;
use App\Models\Couple;
use App\Models\Duty;
use App\Models\DutyReminderBatch;
use App\Models\User;
use App\Services\Notifications\NotificationOutbox;
use App\Services\Requests\RequestContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class CreateDutyReminders
{
    public const HOURS = [10, 15, 20];

    public const WINDOW_MINUTES = 30;

    public const STATUSES = [DutyStatus::Pending, DutyStatus::Overdue, DutyStatus::Disputed];

    public function __construct(private readonly RequestContext $context, private readonly NotificationOutbox $outbox) {}

    public function execute(User $actor, Couple $couple, CarbonImmutable $slot): void
    {
        DB::transaction(function () use ($actor, $couple, $slot): void {
            $current = $this->context->lockCouple($actor, $couple);
            $local = $slot->setTimezone($current->timezone);
            if (! in_array($local->hour, self::HOURS, true) || $local->minute !== 0 || $slot->isFuture() || $slot->addMinutes(self::WINDOW_MINUTES)->lessThanOrEqualTo(CarbonImmutable::now())) {
                return;
            }
            if (DutyReminderBatch::query()->where('couple_id', $current->id)->where('user_id', $actor->id)->where('scheduled_at', $slot)->lockForUpdate()->first() !== null || Duty::query()->where('couple_id', $current->id)->where('executor_id', $actor->id)->whereIn('status', self::STATUSES)->lockForUpdate()->first() === null) {
                return;
            }
            $batch = new DutyReminderBatch;
            $batch->forceFill(['couple_id' => $current->id, 'user_id' => $actor->id, 'scheduled_at' => $slot])->save();
            $this->outbox->record($current->id, $actor->id, NotificationKind::DutyReminder, $batch->id, expiresAt: $slot->addMinutes(self::WINDOW_MINUTES));
        }, attempts: 3);
    }
}
