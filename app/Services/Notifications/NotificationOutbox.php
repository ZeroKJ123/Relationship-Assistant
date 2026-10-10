<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationKind;
use App\Jobs\SendTelegramNotificationJob;
use App\Models\NotificationDelivery;
use App\Models\TelegramAccount;
use App\Services\Telegram\BotClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

final class NotificationOutbox
{
    public function __construct(private readonly BotClient $bot) {}

    public function record(int $coupleId, int $recipientId, NotificationKind $kind, int $sourceId, ?int $sourceVersion = null, ?CarbonImmutable $expiresAt = null): NotificationDelivery
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Notification outbox must be recorded in the domain transaction.');
        }
        $key = sprintf('%s:%d', $kind->value, $sourceId);
        $existing = NotificationDelivery::query()->where('recipient_id', $recipientId)->where('operation_key', $key)->lockForUpdate()->first();
        if ($existing !== null) {
            return $existing;
        }
        $account = TelegramAccount::query()->where('user_id', $recipientId)->lockForUpdate()->first();
        $delivery = new NotificationDelivery;
        $delivery->forceFill(['couple_id' => $coupleId, 'recipient_id' => $recipientId, 'kind' => $kind, 'source_id' => $sourceId, 'source_version' => $sourceVersion, 'operation_key' => $key, 'status' => DeliveryStatus::Pending, 'attempts' => 0, 'available_at' => now(), 'expires_at' => $expiresAt ?? CarbonImmutable::now()->addHours(NotificationDelivery::DEFAULT_LIFETIME_HOURS), 'telegram_account_id' => $account?->id, 'chat_fingerprint' => $account === null ? null : hash('sha256', $account->chat_id)])->save();
        DB::afterCommit(function () use ($delivery): void {
            Log::info('notification.recorded', ['delivery_id' => $delivery->id, 'kind' => $delivery->kind->value]);
            $this->dispatch($delivery);
        });

        return $delivery;
    }

    public function dispatch(NotificationDelivery $delivery): void
    {
        if (! $this->bot->isEnabled() || ! $this->hasAsyncQueue()) {
            return;
        }
        if (! $this->reserveQueueSlot($delivery)) {
            return;
        }
        try {
            SendTelegramNotificationJob::dispatch($delivery->id, $delivery->recipient_id)->onQueue('telegram');
        } catch (Throwable $exception) {
            NotificationDelivery::query()->whereKey($delivery->id)->update(['queued_until' => null]);
            Log::error('notification.enqueue_failed', ['delivery_id' => $delivery->id, 'reason' => 'queue_unavailable', 'exception_type' => $exception::class]);
        }
    }

    private function hasAsyncQueue(): bool
    {
        $connection = config()->string('queue.default');
        $driver = config(sprintf('queue.connections.%s.driver', $connection));

        return in_array($driver, ['database', 'redis', 'sqs', 'beanstalkd'], true);
    }

    private function reserveQueueSlot(NotificationDelivery $delivery): bool
    {
        return DB::transaction(function () use ($delivery): bool {
            $current = NotificationDelivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            $isPending = $current->status === DeliveryStatus::Pending && $current->available_at->lessThanOrEqualTo(now());
            $isAbandoned = $current->status === DeliveryStatus::Processing && $current->leased_until !== null && $current->leased_until->lessThanOrEqualTo(now());
            if ((! $isPending && ! $isAbandoned) || ($current->queued_until !== null && $current->queued_until->isFuture())) {
                return false;
            }
            $current->forceFill(['queued_until' => now()->addSeconds(NotificationDelivery::QUEUE_RESERVATION_SECONDS)])->save();

            return true;
        }, attempts: 3);
    }
}
