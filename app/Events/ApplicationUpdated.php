<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

final class ApplicationUpdated implements ShouldBroadcast
{
    use Dispatchable;

    public bool $afterCommit = true;

    public int $tries = 5;

    public int $timeout = 10;

    public int $backoff = 5;

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('application.updates');
    }

    public function broadcastAs(): string
    {
        return 'application.updated';
    }

    /** @return array<string, never> */
    public function broadcastWith(): array
    {
        return [];
    }
}
