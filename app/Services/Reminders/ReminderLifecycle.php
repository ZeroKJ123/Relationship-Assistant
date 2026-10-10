<?php

declare(strict_types=1);

namespace App\Services\Reminders;

use App\Enums\ReminderStatus;
use App\Models\CoupleMember;
use App\Models\PartnerReminder;
use App\Services\Couples\CoupleLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class ReminderLifecycle
{
    public function __construct(private readonly CoupleLock $lock) {}

    public function lockReminder(PartnerReminder $reminder): PartnerReminder
    {
        $ids = CoupleMember::query()->where('couple_id', $reminder->couple_id)->orderBy('user_id')->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all();
        $this->lock->lockUsers(array_values($ids));
        $couple = $this->lock->lockCouple($reminder->couple_id);
        $current = PartnerReminder::query()->where('couple_id', $couple->id)->whereKey($reminder->id)->lockForUpdate()->firstOrFail();
        $current->setRelation('couple', $couple);

        return $current;
    }

    public function transition(PartnerReminder $reminder, ReminderStatus $status): void
    {
        $payload = ['status' => $status, 'version' => $reminder->version + 1];
        if ($status === ReminderStatus::Cancelled) {
            $payload['cancelled_at'] = now();
        }
        if ($status === ReminderStatus::Ready) {
            $payload['prepared_at'] = now();
        }
        $reminder->forceFill($payload)->save();
        DB::afterCommit(fn () => Log::info('reminder.transitioned', ['reminder_id' => $reminder->id, 'status' => $status->value, 'version' => $reminder->version]));
    }

    public function deny(PartnerReminder $reminder): never
    {
        Log::warning('reminder.denied', ['reminder_id' => $reminder->id, 'reason' => 'stale_or_unavailable']);
        throw ValidationException::withMessages(['reminder' => __('Напоминание изменилось или уже доставлено. Обновите страницу.')]);
    }
}
