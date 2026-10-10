<?php

declare(strict_types=1);

namespace App\Data\Telegram;

final readonly class OutgoingMessage
{
    public function __construct(public int $deliveryId, public string $leaseToken, public string $chatId, public string $text) {}
}
