<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Scheduling\DueCare;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class ProcessDueCareCommand extends Command
{
    protected $signature = 'care:process-due';

    protected $description = 'Prepare due reminders and the latest still available mood prompt in each couple timezone';

    public function handle(DueCare $care): int
    {
        Log::debug('schedule.care_started');
        $care->execute();
        Log::debug('schedule.care_completed');

        return self::SUCCESS;
    }
}
