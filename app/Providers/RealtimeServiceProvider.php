<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Realtime\ApplicationChanges;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class RealtimeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(QueryExecuted::class, ApplicationChanges::class);
    }
}
