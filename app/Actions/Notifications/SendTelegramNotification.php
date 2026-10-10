<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\NotificationDelivery;
use App\Services\Notifications\DeliveryLifecycle;
use App\Services\Telegram\BotClient;

final class SendTelegramNotification
{
    public function __construct(private readonly BotClient $bot, private readonly DeliveryLifecycle $lifecycle) {}

    public function execute(int $deliveryId): void
    {
        if (! $this->bot->isEnabled()) {
            return;
        }
        $delivery = NotificationDelivery::query()->find($deliveryId);
        if ($delivery === null) {
            return;
        }
        $message = $this->lifecycle->claim($delivery);
        if ($message === null) {
            return;
        }
        $result = $this->bot->sendMessage($message->chatId, $message->text);
        $this->lifecycle->complete($delivery->refresh(), $message->leaseToken, $result);
    }
}
