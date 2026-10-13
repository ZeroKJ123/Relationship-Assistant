<?php

declare(strict_types=1);

namespace App\Actions\Auth\Telegram;

use App\Enums\Currency;
use App\Enums\Gender;
use App\Enums\Locale;
use App\Enums\TelegramLoginStatus;
use App\Models\TelegramAccount;
use App\Models\TelegramLoginAttempt;
use App\Models\User;
use App\Services\Auth\RecoveryCodes;
use App\Services\Hearts\Ledger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class FinishTelegramLogin
{
    public function __construct(private readonly Ledger $ledger, private readonly RecoveryCodes $recoveryCodes) {}

    /** @return array{user: User, recovery_codes: list<string>, account: TelegramAccount} */
    public function execute(int $attemptId, string $browserToken, ?User $actor): array
    {
        $codes = $this->recoveryCodes->generate();
        $hashes = $this->recoveryCodes->hash($codes);

        return DB::transaction(function () use ($attemptId, $browserToken, $actor, $codes, $hashes): array {
            // Serializes identity creation and linking, including identities without an account row yet.
            DB::table('telegram_auth_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
            $attempt = TelegramLoginAttempt::query()->whereKey($attemptId)->lockForUpdate()->firstOrFail();
            $this->requireConfirmed($attempt, $browserToken, $actor);
            $identity = $attempt->identity;
            if ($identity === null) {
                $this->deny('identity_missing');
            }
            $existing = TelegramAccount::query()->where('telegram_id', $identity['telegram_id'])->first();
            $user = $this->getUser($attempt, $existing, $identity['name'], $hashes);
            $account = TelegramAccount::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if ($account !== null && $account->telegram_id !== $identity['telegram_id']) {
                $this->deny('user_already_linked');
            }
            if ($account === null) {
                $account = new TelegramAccount;
                $account->forceFill(['user_id' => $user->id, 'telegram_id' => $identity['telegram_id'], 'chat_id' => $identity['telegram_id']])->save();
            }
            $attempt->forceFill(['status' => TelegramLoginStatus::Consumed, 'identity' => null, 'confirmation_hash' => null])->save();
            DB::afterCommit(fn () => Log::info('telegram.login_completed', ['attempt_id' => $attempt->id, 'user_id' => $user->id, 'account_id' => $account->id]));

            return ['user' => $user, 'account' => $account, 'recovery_codes' => $existing === null && $attempt->user_id === null ? $codes : []];
        }, attempts: 3);
    }

    private function requireConfirmed(TelegramLoginAttempt $attempt, string $browserToken, ?User $actor): void
    {
        if ($attempt->status !== TelegramLoginStatus::Confirmed || $attempt->expires_at->lessThanOrEqualTo(now()) || ! hash_equals($attempt->browser_hash, hash('sha256', $browserToken)) || $attempt->user_id !== $actor?->id) {
            $this->deny('attempt_unavailable');
        }
    }

    /** @param list<string> $hashes */
    private function getUser(TelegramLoginAttempt $attempt, ?TelegramAccount $existing, string $name, array $hashes): User
    {
        if ($attempt->user_id !== null) {
            if ($existing !== null && $existing->user_id !== $attempt->user_id) {
                $this->deny('identity_already_linked');
            }

            return User::query()->whereKey($attempt->user_id)->lockForUpdate()->firstOrFail();
        }
        if ($existing !== null) {
            return User::query()->whereKey($existing->user_id)->lockForUpdate()->firstOrFail();
        }
        $user = new User;
        $user->forceFill(['login' => null, 'password' => null, 'name' => $name, 'gender' => Gender::Unspecified, 'locale' => Locale::current(), 'currency' => Currency::Byn, 'is_admin' => false, 'recovery_codes' => $hashes])->save();
        $this->ledger->getWallet($user);

        return $user;
    }

    private function deny(string $reason): never
    {
        Log::warning('telegram.login_denied', ['reason' => $reason]);
        throw ValidationException::withMessages(['telegram' => __('Вход или привязка недоступны. Начните новую попытку в своём браузере. Аккаунты автоматически не объединяются.')]);
    }
}
