<?php

declare(strict_types=1);

namespace App\Data\Telegram;

final readonly class OutgoingMessage
{
    /** @param array{inline_keyboard: list<list<array{text: string, callback_data: string}|array{text: string, url: string}>>}|null $keyboard */
    public function __construct(public int $deliveryId, public string $leaseToken, public string $chatId, public string $text, public ?array $keyboard = null) {}
}
