<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Data\Telegram\OutgoingMessage;
use App\Models\NotificationDelivery;
use App\Services\Localization\LocalePreference;
use App\Services\Notifications\DeliveryLifecycle;
use App\Services\Telegram\BotClient;

final class SendTelegramNotification
{
    public function __construct(private readonly BotClient $bot, private readonly DeliveryLifecycle $lifecycle, private readonly LocalePreference $locale) {}

    public function execute(int $deliveryId): void
    {
        if (! $this->bot->isEnabled()) {
            return;
        }
        $delivery = NotificationDelivery::query()->find($deliveryId);
        if ($delivery === null) {
            return;
        }
        $message = $this->locale->runFor($delivery->recipient, fn (): ?OutgoingMessage => $this->lifecycle->claim($delivery));
        if ($message === null) {
            return;
        }
        $result = $this->bot->sendMessage($message->chatId, $message->text, $message->keyboard, isHtml: true);
        $this->lifecycle->complete($delivery->refresh(), $message->leaseToken, $result);
    }
}
