<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Telegram\BotClient;
use App\Services\Telegram\PollTelegramUpdates;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PollTelegramUpdatesCommand extends Command
{
    private const POLL_PAUSE_SECONDS = 1;

    private const FAILURE_PAUSE_SECONDS = 5;

    protected $signature = 'telegram:poll-updates {--once : Process one polling batch}';

    protected $description = 'Receive Telegram messages and callbacks without a public webhook';

    public function handle(BotClient $bot, PollTelegramUpdates $poll): int
    {
        if (config('services.telegram.mode') !== 'polling' || ! $bot->isEnabled()) {
            $this->components->error(__('Получение сообщений выключено. Нужны Telegram и режим polling.'));

            return self::FAILURE;
        }
        $info = $bot->request('getWebhookInfo', []);
        if ($info === null || ! is_string($info['url'] ?? null) || $info['url'] !== '') {
            $this->components->error(__('Не удалось проверить бота или у него включён webhook. Сначала проверьте настройки Telegram.'));

            return self::FAILURE;
        }
        $this->components->info(__('Получение сообщений Telegram включено.'));
        do {
            try {
                $success = $poll->execute();
            } catch (Throwable $exception) {
                Log::error('telegram.poll_failed', ['exception_class' => $exception::class]);
                $success = false;
            }
            if ($this->option('once') === true) {
                return $success ? self::SUCCESS : self::FAILURE;
            }
            sleep($success ? self::POLL_PAUSE_SECONDS : self::FAILURE_PAUSE_SECONDS);
        } while (true);
    }
}
