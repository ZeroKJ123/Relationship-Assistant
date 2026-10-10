<?php

declare(strict_types=1);

namespace App\Actions\Auth\Telegram;

use App\Data\Telegram\IncomingUpdate;
use App\Enums\TelegramLoginStatus;
use App\Models\TelegramLoginAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ConfirmTelegramIdentity
{
    /** @return array<string, mixed> */
    public function execute(IncomingUpdate $update): array
    {
        return DB::transaction(function () use ($update): array {
            if ($update->callbackData !== null) {
                return $this->confirm($update);
            }
            if (preg_match('/^\/start ([A-Za-z0-9]{40})$/D', $update->text ?? '', $matches) !== 1) {
                return ['text' => __('Начните вход или привязку на сайте и откройте полученную ссылку.')];
            }
            $attempt = TelegramLoginAttempt::query()->where('start_hash', hash('sha256', $matches[1]))->lockForUpdate()->first();
            $attempt = $this->requireAvailable($attempt);
            if ($attempt->status !== TelegramLoginStatus::Pending || $attempt->identity !== null) {
                throw ValidationException::withMessages(['telegram' => __('Эта ссылка уже использована. Начните заново на сайте.')]);
            }
            $token = Str::random(StartTelegramLogin::TOKEN_LENGTH);
            $attempt->forceFill(['status' => TelegramLoginStatus::Identified, 'identity' => ['telegram_id' => $update->telegramId, 'name' => $update->name === '' ? __('Участник пары') : $update->name], 'confirmation_hash' => hash('sha256', $token)])->save();
            Log::debug('telegram.identity_received', ['attempt_id' => $attempt->id]);

            return ['text' => sprintf("%s\n%s: %s\n%s", $attempt->user_id === null ? __('Подтвердить вход в приложение?') : __('Привязать этот Telegram к вашему аккаунту?'), __('Код в вашем браузере'), $attempt->verification_code, __('Сверьте код. Подтверждайте только попытку, которую начали сами. Затем вернитесь в исходный браузер.')), 'reply_markup' => ['inline_keyboard' => [[['text' => __('Подтверждаю'), 'callback_data' => sprintf('login:%s', $token)]]]]];
        }, attempts: 3);
    }

    /** @return array<string, mixed> */
    private function confirm(IncomingUpdate $update): array
    {
        if (preg_match('/^login:([A-Za-z0-9]{40})$/D', $update->callbackData ?? '', $matches) !== 1) {
            throw ValidationException::withMessages(['telegram' => __('Кнопка недействительна.')]);
        }
        $attempt = TelegramLoginAttempt::query()->where('confirmation_hash', hash('sha256', $matches[1]))->lockForUpdate()->first();
        $attempt = $this->requireAvailable($attempt);
        if ($attempt->status !== TelegramLoginStatus::Identified || ($attempt->identity['telegram_id'] ?? null) !== $update->telegramId) {
            throw ValidationException::withMessages(['telegram' => __('Подтверждение недоступно.')]);
        }
        $attempt->forceFill(['status' => TelegramLoginStatus::Confirmed, 'confirmation_hash' => null])->save();
        Log::info('telegram.identity_confirmed', ['attempt_id' => $attempt->id]);

        return ['text' => __('Подтверждено. Вернитесь в исходный браузер, проверьте имя и завершите вход или привязку.')];
    }

    private function requireAvailable(?TelegramLoginAttempt $attempt): TelegramLoginAttempt
    {
        if ($attempt === null || $attempt->expires_at->lessThanOrEqualTo(now()) || in_array($attempt->status, [TelegramLoginStatus::Consumed, TelegramLoginStatus::Cancelled], true)) {
            throw ValidationException::withMessages(['telegram' => __('Попытка истекла или уже использована. Начните заново на сайте.')]);
        }

        return $attempt;
    }
}
