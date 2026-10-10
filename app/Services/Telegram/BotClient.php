<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Data\Telegram\SendResult;
use App\Enums\DeliveryStatus;
use App\Models\NotificationDelivery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class BotClient
{
    private const API_URL = 'https://api.telegram.org/bot%s/sendMessage';

    private const CONNECT_TIMEOUT_SECONDS = 3;

    private const TIMEOUT_SECONDS = 10;

    public function isEnabled(): bool
    {
        $token = config('services.telegram.bot_token');

        return config('services.telegram.enabled') === true && is_string($token) && preg_match('/^[0-9]+:[a-zA-Z0-9_-]+$/D', $token) === 1;
    }

    public function sendMessage(string $chatId, string $text): SendResult
    {
        if (! $this->isEnabled()) {
            return new SendResult(DeliveryStatus::Pending, 'disabled', retryAfter: NotificationDelivery::UNAVAILABLE_RETRY_SECONDS);
        }
        $token = config()->string('services.telegram.bot_token');
        try {
            $response = Http::connectTimeout(self::CONNECT_TIMEOUT_SECONDS)->timeout(self::TIMEOUT_SECONDS)->withoutRedirecting()->post(sprintf(self::API_URL, $token), ['chat_id' => $chatId, 'text' => $text, 'protect_content' => true, 'link_preview_options' => ['is_disabled' => true]]);
        } catch (ConnectionException) {
            Log::warning('telegram.transport_failed', ['reason' => 'connection_uncertain']);

            return new SendResult(DeliveryStatus::Pending, 'connection_uncertain');
        }
        $messageId = $response->json('result.message_id');
        if ($response->successful() && $response->json('ok') === true && is_int($messageId)) {
            return new SendResult(DeliveryStatus::Sent, 'sent', (string) $messageId);
        }
        $code = $response->json('error_code');
        $status = is_int($code) ? $code : $response->status();
        if ($status === Response::HTTP_TOO_MANY_REQUESTS) {
            $retryAfter = $response->json('parameters.retry_after');

            return new SendResult(DeliveryStatus::Pending, 'rate_limited', retryAfter: is_int($retryAfter) ? max(1, $retryAfter) : NotificationDelivery::UNAVAILABLE_RETRY_SECONDS);
        }
        if ($status === Response::HTTP_FORBIDDEN) {
            return new SendResult(DeliveryStatus::Failed, 'blocked');
        }
        if (in_array($status, [Response::HTTP_BAD_REQUEST, Response::HTTP_UNAUTHORIZED, Response::HTTP_NOT_FOUND], true)) {
            return new SendResult(DeliveryStatus::Failed, 'rejected');
        }

        return new SendResult(DeliveryStatus::Pending, 'api_unavailable');
    }
}
