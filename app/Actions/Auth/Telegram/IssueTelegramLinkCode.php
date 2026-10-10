<?php

declare(strict_types=1);

namespace App\Actions\Auth\Telegram;

use App\Data\Telegram\IncomingUpdate;
use App\Models\TelegramAccount;
use App\Models\TelegramLinkCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class IssueTelegramLinkCode
{
    public const LIFETIME_MINUTES = 10;

    /** @return array{text: string, protect_content?: bool, reply_markup?: array{inline_keyboard: list<list<array{text: string, copy_text: array{text: string}}>>}} */
    public function execute(IncomingUpdate $update): array
    {
        return DB::transaction(function () use ($update): array {
            DB::table('telegram_auth_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
            if (TelegramAccount::query()->where('telegram_id', $update->telegramId)->exists()) {
                return ['text' => __('Telegram уже связан с приложением. Уведомления будут приходить в этот чат.')];
            }
            $code = Str::uuid()->toString();
            $link = TelegramLinkCode::query()->where('identity_hash', hash('sha256', $update->telegramId))->lockForUpdate()->first() ?? new TelegramLinkCode;
            $link->forceFill(['identity_hash' => hash('sha256', $update->telegramId), 'code_hash' => hash('sha256', $code), 'identity' => ['telegram_id' => $update->telegramId], 'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES), 'consumed_at' => null])->save();
            Log::info('telegram.link_code_issued', ['code_id' => $link->id]);

            return ['text' => sprintf("%s\n\n%s\n\n%s", __('Ваш код для привязки Telegram:'), $code, __('Нажмите кнопку с кодом, чтобы скопировать его. Откройте свой профиль в приложении и вставьте код в разделе «Связь с Telegram». Код действует 10 минут и работает один раз. Не передавайте его другим. Новый /start заменит прежний код.')), 'protect_content' => false, 'reply_markup' => ['inline_keyboard' => [[['text' => $code, 'copy_text' => ['text' => $code]]]]]];
        }, attempts: 3);
    }
}
