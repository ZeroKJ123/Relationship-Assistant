<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TelegramCallbackAction;
use App\Models\TelegramLinkCode;
use App\Models\TelegramLoginAttempt;
use App\Models\TelegramUpdateReceipt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class PruneTelegramControlsCommand extends Command
{
    private const RETENTION_DAYS = 7;

    protected $signature = 'telegram:prune-controls';

    protected $description = 'Remove expired Telegram controls and old encrypted webhook replies';

    public function handle(): int
    {
        $cutoff = now()->subDays(self::RETENTION_DAYS);
        $attempts = TelegramLoginAttempt::query()->where('expires_at', '<', $cutoff)->delete();
        $actions = TelegramCallbackAction::query()->where('expires_at', '<', $cutoff)->delete();
        $receipts = TelegramUpdateReceipt::query()->where('created_at', '<', $cutoff)->delete();
        $codes = TelegramLinkCode::query()->where('expires_at', '<', $cutoff)->delete();
        Log::info('telegram.controls_pruned', ['attempts' => $attempts, 'actions' => $actions, 'receipts' => $receipts, 'codes' => $codes]);
        $this->components->info(__('Старые подтверждения Telegram удалены.'));

        return self::SUCCESS;
    }
}
