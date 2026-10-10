<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Data\Telegram\OutgoingMessage;
use App\Data\Telegram\SendResult;
use App\Enums\CoupleStatus;
use App\Enums\DeliveryStatus;
use App\Enums\NotificationKind;
use App\Enums\ReminderStatus;
use App\Models\CoupleMember;
use App\Models\NotificationDelivery;
use App\Models\PartnerReminder;
use App\Models\TelegramAccount;
use App\Services\Couples\CoupleLock;
use App\Services\Reminders\ReminderLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class DeliveryLifecycle
{
    public function __construct(private readonly CoupleLock $lock, private readonly DeliveryContent $content, private readonly ReminderLifecycle $reminders) {}

    public function claim(NotificationDelivery $delivery): ?OutgoingMessage
    {
        return DB::transaction(function () use ($delivery): ?OutgoingMessage {
            $this->lockContext($delivery);
            $current = NotificationDelivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            $current->setRelation('couple', $delivery->couple);
            if (! $this->isAvailable($current)) {
                return null;
            }
            if ($current->couple->status !== CoupleStatus::Active || $current->couple->members()->where('active_user_id', $current->recipient_id)->lockForUpdate()->first() === null) {
                $this->finish($current, DeliveryStatus::Cancelled, 'couple_ended');

                return null;
            }
            if ($current->expires_at->lessThan(now())) {
                $this->finish($current, DeliveryStatus::Failed, 'expired');

                return null;
            }
            $text = $this->content->getText($current);
            if ($text === null) {
                $this->finish($current, DeliveryStatus::Cancelled, 'stale_source');

                return null;
            }
            if ($current->attempts >= NotificationDelivery::MAXIMUM_ATTEMPTS) {
                $this->finish($current, DeliveryStatus::Failed, 'attempts_exhausted');

                return null;
            }
            $account = TelegramAccount::query()->where('user_id', $current->recipient_id)->lockForUpdate()->first();
            if ($account === null) {
                $current->forceFill(['status' => DeliveryStatus::Pending, 'available_at' => now()->addSeconds(NotificationDelivery::UNAVAILABLE_RETRY_SECONDS), 'queued_until' => null, 'lease_token' => null, 'leased_until' => null, 'failure_reason' => 'account_unavailable'])->save();

                return null;
            }
            if (! $this->hasValidAccount($current, $account)) {
                $this->finish($current, DeliveryStatus::Cancelled, 'account_changed');

                return null;
            }
            $token = Str::uuid()->toString();
            $current->forceFill(['status' => DeliveryStatus::Processing, 'attempts' => $current->attempts + 1, 'queued_until' => null, 'lease_token' => $token, 'leased_until' => now()->addSeconds(NotificationDelivery::LEASE_SECONDS), 'telegram_account_id' => $account->id, 'chat_fingerprint' => hash('sha256', $account->chat_id), 'failure_reason' => null])->save();
            $this->startReminder($current);
            Log::debug('notification.claimed', ['delivery_id' => $current->id, 'attempt' => $current->attempts]);

            return new OutgoingMessage($current->id, $token, $account->chat_id, $text);
        }, attempts: 3);
    }

    public function complete(NotificationDelivery $delivery, string $token, SendResult $result): void
    {
        DB::transaction(function () use ($delivery, $token, $result): void {
            $this->lockContext($delivery);
            $current = NotificationDelivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            if ($current->status !== DeliveryStatus::Processing || $current->lease_token !== $token) {
                Log::warning('notification.result_ignored', ['delivery_id' => $current->id, 'reason' => 'lease_changed']);

                return;
            }
            if ($result->status === DeliveryStatus::Sent) {
                $current->forceFill(['message_id' => $result->messageId, 'sent_at' => now()])->save();
                $this->finish($current, DeliveryStatus::Sent, 'sent');

                return;
            }
            if ($result->status === DeliveryStatus::Failed || $current->attempts >= NotificationDelivery::MAXIMUM_ATTEMPTS) {
                $this->finish($current, DeliveryStatus::Failed, $result->status === DeliveryStatus::Failed ? $result->reason : 'attempts_exhausted');

                return;
            }
            $delay = max($result->retryAfter, NotificationDelivery::BACKOFF_SECONDS[$current->attempts - 1]);
            $current->forceFill(['status' => DeliveryStatus::Pending, 'failure_reason' => $result->reason, 'available_at' => now()->addSeconds($delay), 'queued_until' => null, 'lease_token' => null, 'leased_until' => null])->save();
            Log::warning('notification.retry_scheduled', ['delivery_id' => $current->id, 'attempt' => $current->attempts, 'reason' => $result->reason]);
        }, attempts: 3);
    }

    private function lockContext(NotificationDelivery $delivery): void
    {
        $ids = CoupleMember::query()->where('couple_id', $delivery->couple_id)->orderBy('user_id')->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all();
        $this->lock->lockUsers(array_values($ids));
        $delivery->setRelation('couple', $this->lock->lockCouple($delivery->couple_id));
    }

    private function isAvailable(NotificationDelivery $delivery): bool
    {
        if ($delivery->status === DeliveryStatus::Processing) {
            return $delivery->leased_until !== null && $delivery->leased_until->lessThanOrEqualTo(now());
        }

        return $delivery->status === DeliveryStatus::Pending && $delivery->available_at->lessThanOrEqualTo(now());
    }

    private function hasValidAccount(NotificationDelivery $delivery, TelegramAccount $account): bool
    {
        if ($account->chat_id !== $account->telegram_id || preg_match('/^[1-9][0-9]{0,18}$/D', $account->chat_id) !== 1) {
            return false;
        }

        return ($delivery->telegram_account_id === null || $delivery->telegram_account_id === $account->id) && ($delivery->chat_fingerprint === null || hash_equals($delivery->chat_fingerprint, hash('sha256', $account->chat_id)));
    }

    private function startReminder(NotificationDelivery $delivery): void
    {
        if ($delivery->kind !== NotificationKind::Reminder) {
            return;
        }
        $reminder = PartnerReminder::query()->whereKey($delivery->source_id)->where('couple_id', $delivery->couple_id)->where('recipient_id', $delivery->recipient_id)->lockForUpdate()->firstOrFail();
        if ($reminder->status === ReminderStatus::Ready) {
            $this->reminders->transition($reminder, ReminderStatus::Sending);
            $delivery->forceFill(['source_version' => $reminder->version])->save();
        }
    }

    private function finish(NotificationDelivery $delivery, DeliveryStatus $status, string $reason): void
    {
        $delivery->forceFill(['status' => $status, 'failure_reason' => $status === DeliveryStatus::Sent ? null : $reason, 'queued_until' => null, 'lease_token' => null, 'leased_until' => null])->save();
        $this->finishReminder($delivery, $status, $reason);
        DB::afterCommit(fn () => Log::log($status === DeliveryStatus::Failed ? 'error' : 'info', 'notification.transitioned', ['delivery_id' => $delivery->id, 'status' => $status->value, 'reason' => $reason, 'attempts' => $delivery->attempts]));
    }

    private function finishReminder(NotificationDelivery $delivery, DeliveryStatus $status, string $reason): void
    {
        if ($delivery->kind !== NotificationKind::Reminder) {
            return;
        }
        $reminder = PartnerReminder::query()->whereKey($delivery->source_id)->where('couple_id', $delivery->couple_id)->where('recipient_id', $delivery->recipient_id)->lockForUpdate()->first();
        if ($reminder === null || ! in_array($reminder->status, [ReminderStatus::Ready, ReminderStatus::Sending], true)) {
            return;
        }
        $target = match ($status) {
            DeliveryStatus::Sent => ReminderStatus::Delivered,
            DeliveryStatus::Cancelled => ReminderStatus::Cancelled,
            default => $reason === 'expired' ? ReminderStatus::Expired : ReminderStatus::Failed,
        };
        $this->reminders->transition($reminder, $target);
    }
}
