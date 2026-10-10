<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('duties:check-overdue')->everyMinute()->withoutOverlapping();

Schedule::command('care:process-due')->everyMinute()->withoutOverlapping();
Schedule::command('telegram:dispatch-notifications')->everyMinute()->withoutOverlapping();

Schedule::command('telegram:prune-controls')->daily()->withoutOverlapping();
