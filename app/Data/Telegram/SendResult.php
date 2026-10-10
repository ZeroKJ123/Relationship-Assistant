<?php

declare(strict_types=1);

namespace App\Data\Telegram;

use App\Enums\DeliveryStatus;

final readonly class SendResult
{
    public function __construct(public DeliveryStatus $status, public string $reason, public ?string $messageId = null, public int $retryAfter = 0) {}
}
