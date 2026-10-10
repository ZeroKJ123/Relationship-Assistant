<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Data\Telegram\IncomingUpdate;
use App\Enums\DeliveryStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class PollTelegramUpdates
{
    private const LOCK_SECONDS = 120;

    public function __construct(private readonly BotClient $bot, private readonly WebhookHandler $handler) {}

    public function execute(): bool
    {
        if (! $this->bot->isEnabled()) {
            return false;
        }
        $key = sprintf('telegram-poll:%s', hash('sha256', config()->string('services.telegram.bot_token')));
        $lock = Cache::lock(sprintf('%s:lock', $key), self::LOCK_SECONDS);
        if (! $lock->get()) {
            Log::warning('telegram.poll_busy');

            return false;
        }
        try {
            $retryAt = Cache::get(sprintf('%s:retry', $key));
            if (is_int($retryAt) && $retryAt > now()->getTimestamp()) {
                return true;
            }
            $offset = Cache::get($key, 0);
            $offset = is_int($offset) && $offset >= 0 ? $offset : 0;
            $updates = $this->bot->request('getUpdates', ['offset' => $offset, 'limit' => 1, 'timeout' => 3, 'allowed_updates' => ['message', 'callback_query']]);
            if ($updates === null) {
                return false;
            }
            foreach ($updates as $payload) {
                if (! is_array($payload) || ! is_int($payload['update_id'] ?? null) || $payload['update_id'] < 0) {
                    Log::warning('telegram.poll_invalid_update');

                    return false;
                }
                /** @var array<string, mixed> $payload */
                if (! $this->process($payload, $key)) {
                    return false;
                }
                Cache::forever($key, $payload['update_id'] + 1);
            }

            return true;
        } finally {
            $lock->release();
        }
    }

    /** @param array<string, mixed> $payload */
    private function process(array $payload, string $key): bool
    {
        $update = IncomingUpdate::fromArray($payload);
        if ($update === null) {
            return true;
        }
        $reply = $this->handler->execute($update, hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)));
        if ($update->callbackId !== null) {
            $this->bot->answerCallback($update->callbackId);
        }
        if ($reply === []) {
            return true;
        }
        $text = $reply['text'] ?? null;
        if (! is_string($text)) {
            Log::warning('telegram.poll_invalid_reply');

            return false;
        }
        /** @var array{inline_keyboard: list<list<array{text: string, callback_data: string}|array{text: string, url: string}|array{text: string, copy_text: array{text: string}}>>}|null $keyboard */
        $keyboard = $reply['reply_markup'] ?? null;
        $result = $this->bot->sendMessage($update->telegramId, $text, $keyboard, protectContent: ($reply['protect_content'] ?? true) !== false);
        if ($result->status === DeliveryStatus::Pending) {
            Cache::put(sprintf('%s:retry', $key), now()->getTimestamp() + max(5, $result->retryAfter), now()->addDay());

            return false;
        }
        Log::debug('telegram.poll_processed', ['update_id' => $update->updateId, 'reply_status' => $result->status->value]);

        return true;
    }
}
