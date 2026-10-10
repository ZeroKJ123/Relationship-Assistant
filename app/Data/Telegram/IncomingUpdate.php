<?php

declare(strict_types=1);

namespace App\Data\Telegram;

final readonly class IncomingUpdate
{
    public function __construct(public int $updateId, public string $telegramId, public string $name, public ?string $text, public ?string $callbackData, public ?string $callbackId) {}

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): ?self
    {
        $updateId = $payload['update_id'] ?? null;
        $callback = $payload['callback_query'] ?? null;
        $message = is_array($callback) ? ($callback['message'] ?? null) : ($payload['message'] ?? null);
        if (! is_int($updateId) || $updateId < 0 || ! is_array($message)) {
            return null;
        }
        $from = is_array($callback) ? ($callback['from'] ?? null) : ($message['from'] ?? null);
        $chat = $message['chat'] ?? null;
        if (! is_array($from) || ! is_array($chat) || ($from['is_bot'] ?? true) !== false || ($chat['type'] ?? null) !== 'private') {
            return null;
        }
        $actorId = $from['id'] ?? null;
        if (! is_int($actorId) || $actorId < 1 || ($chat['id'] ?? null) !== $actorId) {
            return null;
        }
        $name = $from['first_name'] ?? '';
        $text = $message['text'] ?? null;
        $callbackData = is_array($callback) ? ($callback['data'] ?? null) : null;
        $callbackId = is_array($callback) ? ($callback['id'] ?? null) : null;

        return new self($updateId, (string) $actorId, is_string($name) ? mb_substr(trim($name), 0, 80) : '', is_string($text) ? $text : null, is_string($callbackData) ? $callbackData : null, is_string($callbackId) ? $callbackId : null);
    }
}
