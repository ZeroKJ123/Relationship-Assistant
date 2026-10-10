<?php

declare(strict_types=1);

namespace App\Actions\Auth\Telegram;

use App\Enums\TelegramLoginStatus;
use App\Models\TelegramLoginAttempt;
use App\Models\User;
use App\Services\Telegram\BotClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StartTelegramLogin
{
    public const LIFETIME_MINUTES = 10;

    public const TOKEN_LENGTH = 40;

    public function __construct(private readonly BotClient $bot) {}

    public function cancel(int $attemptId, string $browserToken): void
    {
        DB::transaction(function () use ($attemptId, $browserToken): void {
            $attempt = TelegramLoginAttempt::query()->whereKey($attemptId)->lockForUpdate()->firstOrFail();
            if (! hash_equals($attempt->browser_hash, hash('sha256', $browserToken)) || $attempt->status === TelegramLoginStatus::Consumed) {
                throw ValidationException::withMessages(['telegram' => __('Попытка недоступна.')]);
            }
            $attempt->forceFill(['status' => TelegramLoginStatus::Cancelled, 'identity' => null, 'confirmation_hash' => null])->save();
            Log::info('telegram.login_cancelled', ['attempt_id' => $attempt->id]);
        }, attempts: 3);
    }

    /** @return array{attempt: TelegramLoginAttempt, browser_token: string, start_token: string} */
    public function execute(?User $user): array
    {
        if (! $this->bot->isLoginAvailable()) {
            throw ValidationException::withMessages(['telegram' => __('Вход через Telegram пока недоступен. Используйте логин или резервный код.')]);
        }
        $startToken = Str::random(self::TOKEN_LENGTH);
        $browserToken = Str::random(self::TOKEN_LENGTH);
        $attempt = new TelegramLoginAttempt;
        $attempt->forceFill(['user_id' => $user?->id, 'start_hash' => hash('sha256', $startToken), 'browser_hash' => hash('sha256', $browserToken), 'confirmation_hash' => null, 'identity' => null, 'verification_code' => (string) random_int(100000, 999999), 'status' => TelegramLoginStatus::Pending, 'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES)])->save();
        Log::info('telegram.login_started', ['attempt_id' => $attempt->id, 'mode' => $user === null ? 'login' : 'link']);

        return ['attempt' => $attempt, 'browser_token' => $browserToken, 'start_token' => $startToken];
    }
}
