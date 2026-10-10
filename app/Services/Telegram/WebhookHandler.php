<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Actions\Auth\Telegram\ConfirmTelegramIdentity;
use App\Data\Telegram\IncomingUpdate;
use App\Models\TelegramUpdateReceipt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class WebhookHandler
{
    public function __construct(private readonly ConfirmTelegramIdentity $login, private readonly CallbackRouter $callbacks) {}

    /** @return array<string, mixed> */
    public function execute(IncomingUpdate $update, string $payloadHash): array
    {
        return DB::transaction(function () use ($update, $payloadHash): array {
            TelegramUpdateReceipt::query()->insertOrIgnore(['update_id' => $update->updateId, 'payload_hash' => $payloadHash, 'reply' => null, 'created_at' => now(), 'updated_at' => now()]);
            $receipt = TelegramUpdateReceipt::query()->where('update_id', $update->updateId)->lockForUpdate()->firstOrFail();
            if (! hash_equals($receipt->payload_hash, $payloadHash)) {
                Log::warning('telegram.update_rejected', ['update_id' => $update->updateId, 'reason' => 'payload_changed']);

                return [];
            }
            if ($receipt->reply !== null) {
                return $receipt->reply;
            }
            Log::debug('telegram.update_started', ['update_id' => $update->updateId]);
            try {
                $reply = DB::transaction(fn (): array => $this->route($update));
            } catch (ValidationException|AuthorizationException|ModelNotFoundException) {
                $reply = ['text' => __('Действие недоступно: кнопка устарела, попытка истекла или не принадлежит вам. Начните заново в приложении.')];
                Log::warning('telegram.update_denied', ['update_id' => $update->updateId, 'reason' => 'state_or_permission']);
            }
            $reply = ['method' => 'sendMessage', 'chat_id' => $update->telegramId, 'protect_content' => true, ...$reply];
            $receipt->forceFill(['reply' => $reply])->save();

            return $reply;
        }, attempts: 3);
    }

    /** @return array<string, mixed> */
    private function route(IncomingUpdate $update): array
    {
        if ($update->callbackData !== null && ! str_starts_with($update->callbackData, 'login:')) {
            return $this->callbacks->execute($update);
        }

        return $this->login->execute($update);
    }
}
