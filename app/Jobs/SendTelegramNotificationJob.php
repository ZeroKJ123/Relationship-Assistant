<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Notifications\SendTelegramNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;

final class SendTelegramNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $maxExceptions = 3;

    public int $timeout = 30;

    public function __construct(public readonly int $deliveryId, public readonly int $recipientId) {}

    /** @return list<RateLimited|WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping(sprintf('telegram:%d', $this->recipientId)))->shared()->releaseAfter(2)->expireAfter(60), new RateLimited('telegram')];
    }

    public function handle(SendTelegramNotification $send): void
    {
        $send->execute($this->deliveryId);
    }
}
