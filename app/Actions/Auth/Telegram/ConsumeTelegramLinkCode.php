<?php

declare(strict_types=1);

namespace App\Actions\Auth\Telegram;

use App\Models\TelegramAccount;
use App\Models\TelegramLinkCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class ConsumeTelegramLinkCode
{
    public function execute(User $actor, string $code): void
    {
        DB::transaction(function () use ($actor, $code): void {
            DB::table('telegram_auth_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
            $link = TelegramLinkCode::query()->where('code_hash', hash('sha256', strtolower(trim($code))))->lockForUpdate()->first();
            if ($link === null || $link->consumed_at !== null || $link->expires_at->lessThanOrEqualTo(now()) || $link->identity === null) {
                $this->deny('code_unavailable');
            }
            $user = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $telegramId = $link->identity['telegram_id'];
            if (TelegramAccount::query()->where('telegram_id', $telegramId)->orWhere('user_id', $user->id)->exists()) {
                $this->deny('account_already_linked');
            }
            $account = new TelegramAccount;
            $account->forceFill(['user_id' => $user->id, 'telegram_id' => $telegramId, 'chat_id' => $telegramId])->save();
            $link->forceFill(['identity' => null, 'consumed_at' => now()])->save();
            DB::afterCommit(fn () => Log::info('telegram.code_link_completed', ['user_id' => $user->id, 'account_id' => $account->id]));
        }, attempts: 3);
    }

    private function deny(string $reason): never
    {
        Log::warning('telegram.code_link_denied', ['reason' => $reason]);
        throw ValidationException::withMessages(['telegram_code' => __('Код истёк, уже использован или аккаунт уже связан. Отправьте /start своему боту и используйте новый код в своём профиле.')]);
    }
}
