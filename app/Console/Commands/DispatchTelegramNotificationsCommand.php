<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DeliveryStatus;
use App\Models\NotificationDelivery;
use App\Services\Notifications\NotificationOutbox;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

final class DispatchTelegramNotificationsCommand extends Command
{
    private const BATCH_SIZE = 100;

    protected $signature = 'telegram:dispatch-notifications';

    protected $description = 'Dispatch due and abandoned Telegram deliveries from the durable outbox';

    public function handle(NotificationOutbox $outbox): int
    {
        $deliveries = NotificationDelivery::query()->where(function (Builder $query): void {
            $query->where(fn (Builder $pending): Builder => $pending->where('status', DeliveryStatus::Pending)->where('available_at', '<=', now()))->orWhere(fn (Builder $processing): Builder => $processing->where('status', DeliveryStatus::Processing)->where('leased_until', '<=', now()));
        })->where(fn (Builder $query): Builder => $query->whereNull('queued_until')->orWhere('queued_until', '<=', now()))->orderBy('available_at')->orderBy('id')->limit(self::BATCH_SIZE)->get();
        foreach ($deliveries as $delivery) {
            $outbox->dispatch($delivery);
        }
        Log::debug('notification.outbox_scanned', ['selected_count' => $deliveries->count()]);

        return self::SUCCESS;
    }
}
